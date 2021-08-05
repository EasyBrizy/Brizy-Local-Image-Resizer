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
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

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
        $imagine = $this->getImagine($mediaBinary, $filter);
        if (!in_array($imagine->getMimeType(), Imagine::resizeableMimeTypes())) {
            return $this->getOriginalMediaResponse($mediaBinary, $name);
        }

        $configuration = $filterManager->getFilterConfiguration()->get('default');
        $configuration = $this->setCustomConfiguration($configuration, $imagine);
        $filterManager->getFilterConfiguration()->set($filter, $configuration);

        if ($imagine->getCropType() == Imagine::BASIC_CROP_TYPE && $imagine->getIw() > $imagine->getImageWidth() && ($imagine->getIh() == "any" || $imagine->getIh() == "*")) {
            return $this->getResponse($mediaBinary, $imagine->getMimeType());
        }

        $binary = new Binary($mediaBinary, $imagine->getMimeType(), $this->getExtensionByMimeType($imagine->getMimeType()));
        $binary = $filterManager->applyFilter($binary, $filter);

        return $this->getResponse($binary->getContent(), $imagine->getMimeType());
    }

    private function getImagine($mediaBinary, $filter): Imagine
    {
        $filter = strtolower($filter);
        $mediaInfo = getimagesizefromstring($mediaBinary);

        parse_str($filter, $output);
        $output = Imagine::normalizeOutput($output);

        $imagine = new Imagine();
        $imagine
            ->setMimeType($mediaInfo['mime'] ?? '')
            ->setImageWidth($mediaInfo[0] ?? 0)
            ->setImageHeight($mediaInfo[1] ?? 0)
            ->setIw($output['iw'])
            ->setIh($output['ih'])
            ->setOx($output['ox'] ?? 0)
            ->setOy($output['oy'] ?? 0)
            ->setCh($output['ch'] ?? 0)
            ->setCw($output['cw'] ?? 0)
            ->setCropType($this->getCropType($filter));

        return $imagine;
    }

    private function setCustomConfiguration(array $configuration, Imagine $imagine): array
    {
        $configuration['filters']['crop_filter_loader']['mimeType'] = $imagine->getMimeType();
        $configuration['filters']['crop_filter_loader']['originalSize'] = [$imagine->getImageWidth(), $imagine->getImageHeight()];
        $configuration['filters']['crop_filter_loader']['requestedData']['imageWidth'] = $imagine->getIw();
        $configuration['filters']['crop_filter_loader']['requestedData']['imageHeight'] = $imagine->getIh();
        switch ($imagine->getCropType()) {
            case Imagine::BASIC_CROP_TYPE:
                $configuration['filters']['crop_filter_loader']['is_advanced'] = false;
                break;
            case Imagine::ADVANCED_CROP_TYPE:
                $configuration['filters']['crop_filter_loader']['requestedData']['offsetX'] = $imagine->getOx();
                $configuration['filters']['crop_filter_loader']['requestedData']['offsetY'] = $imagine->getOy();
                $configuration['filters']['crop_filter_loader']['requestedData']['cropWidth'] = $imagine->getCw();
                $configuration['filters']['crop_filter_loader']['requestedData']['cropHeight'] = $imagine->getCh();
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
        $mediaBinary = @file_get_contents($this->getParameter('brizy_media_url') . '/' . $unique_name);
        if (!$mediaBinary) {
            $mediaBinary = @file_get_contents($this->getParameter('brizy_default_media_url') . '/' . $unique_name);
            if (!$mediaBinary) {
                throw new NotFoundHttpException('Media was not found');
            }
        }

        return $mediaBinary;
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
