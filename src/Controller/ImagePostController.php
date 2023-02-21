<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\ImagePost;
use App\Photo\PhotoFileManager;
use App\Photo\PhotoPonkaficator;
use App\Repository\ImagePostRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\{Request, JsonResponse};
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\Constraints\{Image, NotBlank};
use Symfony\Component\Validator\Validator\ValidatorInterface;

class ImagePostController extends AbstractController
{
    #[Route('/api/images', name: 'app_image_post', methods: ['GET'])]
    public function list(ImagePostRepository $repository): JsonResponse
    {
        $posts = $repository->findBy([], ['createdAt' => 'DESC']);

        return $this->toJson(['items' => $posts]);
    }

    #[Route('/api/images/{id}', name: 'get_image_post_item', methods: ['GET'])]
    public function getItem(ImagePost $imagePost): JsonResponse
    {
        return $this->toJson($imagePost);
    }

    #[Route('/api/images', methods: ["POST"])]
    public function create(
        Request $request,
        ValidatorInterface $validator,
        PhotoFileManager $photoManager,
        EntityManagerInterface $entityManager,
        PhotoPonkaficator $ponkaficator
    ): JsonResponse {
        /** @var UploadedFile $imageFile */
        $imageFile = $request->files->get('file');

        $errors = $validator->validate($imageFile, [
            new Image(),
            new NotBlank()
        ]);

        if (count($errors) > 0) {
            return $this->toJson($errors, 400);
        }

        $newFilename = $photoManager->uploadImage($imageFile);
        $imagePost = new ImagePost();
        $imagePost->setFilename($newFilename);
        $imagePost->setOriginalFilename($imageFile->getClientOriginalName());

        $entityManager->persist($imagePost);
        $entityManager->flush();

        /*
         * Start Ponkafication!
         */
        $updatedContents = $ponkaficator->ponkafy(
            $photoManager->read($imagePost->getFilename())
        );

        $photoManager->update($imagePost->getFilename(), $updatedContents);
        $imagePost->markAsPonkaAdded();
        $entityManager->flush();
        /*
         * You've been Ponkafied!
         */

        return $this->toJson($imagePost, 201);
    }

    #[Route('/api/images/{id}', name: 'get_image_post_delete', methods: ['DELETE'])]
    public function delete(
        ImagePost $imagePost,
        EntityManagerInterface $entityManager,
        PhotoFileManager $photoManager
    ): JsonResponse {

        $photoManager->deleteImage($imagePost->getFilename());

        $entityManager->remove($imagePost);
        $entityManager->flush();

        return new JsonResponse(null, 204);
    }


    private function toJson($data, int $status = 200, array $headers = [], array $context = []): JsonResponse
    {
        // add the image:output group by default
        if (!isset($context['groups'])) {
            $context['groups'] = ['image:output'];
        }

        return $this->json($data, $status, $headers, $context);
    }
}
