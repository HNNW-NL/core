<?php
namespace App\Twig\Extensions;

use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;
use DateTimeInterface;

/**
 * ArrayExtension
 * 
 * Twig extension with geniric helpfull functions
 */
final class AppExtension extends AbstractExtension
{
    public function __construct()
    {
    }

    /**
     * Register Twig functions provided by this extension
     * 
     * @return TwigFunction[]
     */
    public function getFilters(): array
    {
        return [
            new TwigFilter('cast_to_array', array($this, 'objectToArray')),
            new TwigFilter('display', array($this, 'display')),
        ];
    }

    public function objectToArray(object $object) {
        $response = (array)$object;

        return $response;
    }

    public function display(mixed $object) {
        $response = $object;

        if(is_a($object,DateTimeInterface::class) )
        {
            $response = $object->format('Y-m-d H:i:s');
        }else if(is_object($object))
        {
            $response = (string) $object;
        }

        return $response;
    }
}
