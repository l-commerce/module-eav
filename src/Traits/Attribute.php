<?php

namespace Eav\Traits;

use Cache;
use Eav\Attribute\Collection;

/**
 * Trait Attribute
 *
 * @package Eav\Traits
 */
trait Attribute
{
    /**
     * @var array
     */
    protected static $attributesCollection = [];

    /**
     * @var array
     */
    protected static $attributesCollectionKeys = [];

    /**
     * @param array $attributes
     * @param bool  $static
     * @param bool  $required
     *
     * @return mixed
     */
    public function loadAttributes($attributes = [], $static = false, $required = false)
    {
        $attributes = collect($attributes)->unique();
        $code = $this->baseEntity()->code();

        if ($attributes->isEmpty()) {
            $this->saveAttribute(
                $this->fetchAttributes([], $static, $required)
            );
        } else {
            $newAttribute = $attributes->diff(array_get(static::$attributesCollectionKeys, $code, []));
            if ($newAttribute->isNotEmpty()) {
                $this->saveAttribute(
                    $this->fetchAttributes($newAttribute->all(), $static, $required)
                );
            }
        }

        if ($attributes->isEmpty()) {
            return static::$attributesCollection[$code];
        }
        return static::$attributesCollection[$code]->only($attributes->all())->patch();
    }

    /**
     * @param \Eav\Attribute\Collection $loadedAttributes
     */
    protected function saveAttribute(Collection $loadedAttributes)
    {
        $code = $this->baseEntity()->code();
        if (!isset(static::$attributesCollection[$code])) {
            static::$attributesCollection[$code] = $loadedAttributes;
        } else {
            static::$attributesCollection[$code] = static::$attributesCollection[$code]->merge($loadedAttributes);
        }

        static::$attributesCollectionKeys[$code] = static::$attributesCollection[$code]->code()->toArray();
    }

    /**
     * @param array $attributes
     * @param bool  $static
     * @param bool  $required
     *
     * @return mixed
     */
    protected function fetchAttributes($attributes = [], $static = false, $required = false)
    {
        $query = $this->baseEntity()
            ->attributes()
            ->where(function ($query) use ($static, $required, $attributes) {
                if (!empty($attributes)) {
                    $query->orWhereIn('attribute_code', $attributes);
                }

                if ($static) {
                    $query->orWhere('backend_type', 'static');
                }
                if ($required) {
                    $query->orWhere('is_required', 1);
                }
            });
        $cacheKey = md5(implode('|', $attributes)."|{$this->baseEntity()->getCode()}|{$query->toSql()}");
        return Cache::remember($cacheKey, 10, function () use ($query) {
            return $query->get()->patch();
        });
    }

    /**
     * @param $loadedAttributes
     *
     * @return array
     */
    public function getMainTableAttribute($loadedAttributes)
    {
        $mainTableAttributeCollection = $loadedAttributes->filter(function ($attribute) {
            return $attribute->isStatic();
        });

        $mainTableAttribute = $mainTableAttributeCollection->code()->toArray();

        $mainTableAttribute[] = 'entity_id';
        $mainTableAttribute[] = 'attribute_set_id';

        return $mainTableAttribute;
    }
}
