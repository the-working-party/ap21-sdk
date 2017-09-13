<?php

namespace Arkade\Apparel21\Parsers;

use Carbon\Carbon;
use SimpleXMLElement;
use Arkade\Apparel21\Entities;
use Arkade\Apparel21\Contracts;
use Illuminate\Support\Collection;

class ProductParser
{
    /**
     * Reference resolver.
     *
     * @var Contracts\ReferenceResolver
     */
    protected $referenceResolver;

    /**
     * Set reference resolver.
     *
     * @param  Contracts\ReferenceResolver|null $referenceResolver
     * @return static
     */
    public function setReferenceResolver(Contracts\ReferenceResolver $referenceResolver = null)
    {
        $this->referenceResolver = $referenceResolver;

        return $this;
    }

    /**
     * Parse the given SimpleXmlElement to a Product entity.
     *
     * @param  SimpleXMLElement $payload
     * @return Entities\Product
     */
    public function parse(SimpleXMLElement $payload)
    {
        $product = (new Entities\Product)
            ->setIdentifiers(new Collection([
                'ap21.id'   => (string) $payload->Id,
                'ap21.code' => (string) $payload->Code
            ]))
            ->setOptions(new Collection(['colour' => 'Colour', 'size'   => 'Size']))
            ->setTitle((string) $payload->Name)
            ->setDescription((string) $payload->Description)
            ->setUpdatedAt(Carbon::parse((string) $payload->UpdateTimeStamp));

        $this->parseReferences($product, $payload->References);

        foreach ($payload->Clrs->Clr as $colour) {
            foreach ($colour->SKUs->SKU as $sku) {

                $product->getVariants()->push(
                    (new Entities\Variant)
                        ->setIdentifiers(new Collection([
                            'ap21.colour_id'   => (string) $colour->Id,
                            'ap21.colour_code' => (string) $colour->Code,
                            'ap21.id'          => (string) $sku->Id,
                            'ap21.size_code'   => (string) $sku->SizeCode
                        ]))
                        ->setOptions(new Collection([
                            'colour' => (string) $colour->Name,
                            'size'   => (string) $sku->SizeCode
                        ]))
                        ->setTitle(implode(' - ', [(string) $colour->Name, (string) $sku->SizeCode]))
                        ->setSKU((string) $sku->Barcode)
                        ->setStock((int) $sku->FreeStock)
                        ->setPrice((int) ((float) $sku->Price * 100))
                );

            }
        }

        return $product;
    }

    /**
     * Parse references to attributes on product.
     *
     * @param Entities\Product $product
     * @param SimpleXMLElement $payload
     */
    protected function parseReferences(Entities\Product $product, SimpleXMLElement $payload)
    {
        if (! $this->referenceResolver) return;

        foreach ($payload->Reference as $r) {

            $reference = $this->referenceResolver->resolveFromIds(
                (string) $r->ReferenceTypeId,
                (string) $r->Id
            );

            if ($reference) {
                $product->getAttributes()->offsetSet(
                    strtolower($reference->getType()->getCode()),
                    $reference->getCode()
                );
            }

        }
    }
}