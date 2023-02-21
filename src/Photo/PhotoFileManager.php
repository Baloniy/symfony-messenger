<?php

declare(strict_types=1);

namespace App\Photo;

use App\Entity\ImagePost;
use League\Flysystem\Visibility;
use League\Flysystem\FilesystemOperator;
use League\Flysystem\UnableToWriteFile;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class PhotoFileManager
{
    public function __construct(
        private readonly FilesystemOperator $defaultStorage,
        private readonly string             $publicAssetBaseUrl
    ) {
    }

    public function uploadImage(File $file)
    {
        if ($file instanceof UploadedFile) {
            $originalFilename = $file->getClientOriginalName();
        } else {
            $originalFilename = $file->getFilename();
        }

        $newFilename = pathinfo($originalFilename, PATHINFO_FILENAME).'-'.uniqid().'.'.$file->guessExtension();
        $stream = fopen($file->getPathname(), 'r');
        try {
            $this->defaultStorage->writeStream(
                $newFilename,
                $stream,
                [
                    'visibility' => Visibility::PUBLIC
                ]
            );
        } catch (UnableToWriteFile $exception) {
            $exception::atLocation($file->getPathname(), 'Could not write uploaded file "%s"');
        }

        if (is_resource($stream)) {
            fclose($stream);
        }

        return $newFilename;
    }

    public function deleteImage(string $filename): void
    {
        // make it a bit slow
        sleep(3);

        $this->defaultStorage->delete($filename);
    }

    public function getPublicPath(ImagePost $imagePost): string
    {
        return $this->publicAssetBaseUrl.'/'.$imagePost->getFilename();
    }

    public function read(string $filename): string
    {
        return $this->defaultStorage->read($filename);
    }

    public function update(string $filename, string $updatedContents): void
    {
        $stream = fopen('php://temp', 'r+');
        fwrite($stream, $updatedContents);
        rewind($stream);

        $this->defaultStorage->writeStream($filename, $stream);
    }
}
