<?php

namespace SeoBundle\MetaData;

use Pimcore\Twig\Extension\Templating\HeadMeta;
use Pimcore\Twig\Extension\Templating\HeadTitle;
use SeoBundle\MetaData\Extractor\ExtractorInterface;
use SeoBundle\Middleware\MiddlewareDispatcherInterface;
use SeoBundle\Registry\MetaDataExtractorRegistryInterface;
use SeoBundle\Model\SeoMetaData;

class MetaDataProvider implements MetaDataProviderInterface
{
    protected HeadMeta $headMeta;
    protected HeadTitle $headTitle;
    protected MetaDataExtractorRegistryInterface $extractorRegistry;
    protected MiddlewareDispatcherInterface $middlewareDispatcher;

    public function __construct(
        HeadMeta $headMeta,
        HeadTitle $headTitle,
        MetaDataExtractorRegistryInterface $extractorRegistry,
        MiddlewareDispatcherInterface $middlewareDispatcher
    ) {
        $this->headMeta = $headMeta;
        $this->headTitle = $headTitle;
        $this->extractorRegistry = $extractorRegistry;
        $this->middlewareDispatcher = $middlewareDispatcher;
    }

    public function updateSeoElement($element, ?string $locale): void
    {
        $seoMetadata = $this->getSeoMetaData($element, $locale);

        if ($extraProperties = $seoMetadata->getExtraProperties()) {
            foreach ($extraProperties as $key => $value) {
                $this->headMeta->appendProperty($key, $value);
            }
        }

        if ($extraNames = $seoMetadata->getExtraNames()) {
            foreach ($extraNames as $key => $value) {
                $this->headMeta->appendName($key, $value);
            }
        }

        if ($extraHttp = $seoMetadata->getExtraHttp()) {
            foreach ($extraHttp as $key => $value) {
                $this->headMeta->appendHttpEquiv($key, $value);
            }
        }

        if ($schemaBlocks = $seoMetadata->getSchema()) {
            foreach ($schemaBlocks as $schemaBlock) {
                if (is_array($schemaBlock)) {
                    $schemaTag = sprintf('<script type="application/ld+json">%s</script>', json_encode($schemaBlock, JSON_UNESCAPED_UNICODE));
                    $this->headMeta->addRaw($schemaTag);
                }
            }
        }

        if ($raw = $seoMetadata->getRaw()) {
            foreach ($raw as $rawValue) {
                $this->headMeta->addRaw($rawValue);
            }
        }

        if ($seoMetadata->getTitle()) {
            $this->headTitle->set($seoMetadata->getTitle());
        }

        if ($seoMetadata->getMetaDescription()) {
            $this->headMeta->setDescription($seoMetadata->getMetaDescription());
        }
    }

    protected function getSeoMetaData(mixed $element, ?string $locale): SeoMetaData
    {
        $seoMetaData = new SeoMetaData($this->middlewareDispatcher);
        $extractors = $this->getExtractorsForElement($element);
        foreach ($extractors as $extractor) {
            $extractor->updateMetadata($element, $locale, $seoMetaData);
            $this->middlewareDispatcher->dispatchTasks($seoMetaData);
        }

        $this->middlewareDispatcher->dispatchMiddlewareFinisher($seoMetaData);

        return $seoMetaData;
    }

    /**
     * @return array<int, ExtractorInterface>
     */
    protected function getExtractorsForElement($element): array
    {
        return array_filter($this->extractorRegistry->getAll(), static function (ExtractorInterface $extractor) use ($element) {
            return $extractor->supports($element);
        });
    }
}
