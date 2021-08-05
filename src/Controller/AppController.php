<?php

namespace App\Controller;

use App\Imagine\Imagine;
use Liip\ImagineBundle\Imagine\Filter\FilterManager;
use Liip\ImagineBundle\Model\Binary;
use Mimey\MimeMappingBuilder;
use Mimey\MimeTypes;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class AppController extends AbstractController
{
    public function index(FilterManager $filterManager, $filter, $unique_name): Response
    {
        $mediaBinary = $this->getMediaBinary($unique_name);
        if ($filter == Imagine::ORIGINAL_FILTER_NAME) {
            return $this->getOriginalMediaResponse($mediaBinary, $unique_name);
        }

        return $this->resize($filterManager, $mediaBinary, $unique_name, $filter);
    }

    private function resize(FilterManager $filterManager, $mediaBinary, $name, $filter): Response
    {
        $filter = strtolower($filter);
        $mediaInfo = getimagesizefromstring($mediaBinary);
        $mimeType = $mediaInfo['mime'];

        if (!in_array($mimeType, Imagine::resizeableMimeTypes())) {
            return $this->getOriginalMediaResponse($mediaBinary, $name);
        }

        $extension = $this->getExtensionByMimeType($mimeType);
        $originalSize = [$mediaInfo[0], $mediaInfo[1]];
        parse_str($filter, $output);
        $output = Imagine::normalizeOutput($output);
        $cropType = $this->getCropType($filter);

        $configuration = $filterManager->getFilterConfiguration()->get('default');
        $configuration = $this->setCustomConfiguration($configuration, $originalSize, $output, $cropType, $mimeType);
        $filterManager->getFilterConfiguration()->set($filter, $configuration);

        $binary = new Binary($mediaBinary, $mimeType, $extension);
        if ($cropType == Imagine::BASIC_CROP_TYPE && $output['iw'] > $originalSize[0] && ($output['ih'] == "any" || $output['ih'] == "*")) {
            return $this->getResponse($binary->getContent(), $mimeType);
        }

        $binary = $filterManager->applyFilter($binary, $filter);

        return $this->getResponse($binary->getContent(), $mimeType);
    }

    private function setCustomConfiguration(array $configuration, array $originalSize, array $output, $cropType, $mimeType): array
    {
        $configuration['filters']['crop_filter_loader']['mimeType'] = $mimeType;
        $configuration['filters']['crop_filter_loader']['originalSize'] = $originalSize;
        $configuration['filters']['crop_filter_loader']['requestedData']['imageWidth'] = $output['iw'];
        $configuration['filters']['crop_filter_loader']['requestedData']['imageHeight'] = $output['ih'];
        switch ($cropType) {
            case Imagine::BASIC_CROP_TYPE:
                $configuration['filters']['crop_filter_loader']['is_advanced'] = false;
                break;

            case Imagine::ADVANCED_CROP_TYPE:
                $configuration['filters']['crop_filter_loader']['requestedData']['offsetX'] = $output['ox'];
                $configuration['filters']['crop_filter_loader']['requestedData']['offsetY'] = $output['oy'];
                $configuration['filters']['crop_filter_loader']['requestedData']['cropWidth'] = $output['cw'];
                $configuration['filters']['crop_filter_loader']['requestedData']['cropHeight'] = $output['ch'];
                $configuration['filters']['crop_filter_loader']['is_advanced'] = true;
                break;
        }

        return $configuration;
    }

    private function getExtensionByMimeType($mimeType): string
    {
        $builder = MimeMappingBuilder::create();
        $mimes = new MimeTypes($builder->getMapping());
        return $mimes->getExtension($mimeType);
    }

    private function getCropType($filter): int
    {
        if (preg_match(Imagine::BASIC_FILTER_PATTERN, $filter)) {
            return Imagine::BASIC_CROP_TYPE;
        } elseif (preg_match(Imagine::ADVANCED_FILTER_PATTERN, $filter)) {
            return Imagine::ADVANCED_CROP_TYPE;
        } else {
            throw new BadRequestHttpException("Invalid size format.");
        }
    }

    private function getResponse($content, $content_type): Response
    {
        return new Response($content, 200, [
            'Content-Type' => $content_type,
            'Content-Length' => strlen($content),
            'Cache-Control' => Imagine::CACHE_CONTROL_RESPONSE_HEADER_VALUE
        ]);
    }

    private function getMediaBinary($unique_name)
    {
        return file_get_contents('https://s3.amazonaws.com/brizy.cloud/default_media/0041b7f7c67b70e3c737c3ce1f44c48cb4ee7a22.jpeg');
    }

    private function getOriginalMediaResponse($mediaBinary, $unique_name): Response
    {
        $path_parts = pathinfo($unique_name);
        if (!isset($path_parts['extension']) || $path_parts['extension'] == '') {
            throw new BadRequestHttpException('Invalid file name');
        }

        return new Response($mediaBinary, 200, [
            'Content-Type' => (new MimeTypes())->getMimeType($path_parts['extension'])
        ]);
    }
}
