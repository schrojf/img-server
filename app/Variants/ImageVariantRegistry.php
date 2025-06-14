<?php

namespace App\Variants;

use Closure;
use InvalidArgumentException;

class ImageVariantRegistry
{
    /**
     * @var Closure[] List of lazy variant resolvers.
     */
    protected static array $resolvers = [];

    public static function register(Closure $resolver): void
    {
        static::$resolvers[] = $resolver;
    }

    /**
     * Resolve all variants. Ensures names are unique.
     *
     * @return array<string, ImageVariant>
     */
    public static function all(): array
    {
        $variants = [];

        foreach (static::$resolvers as $resolver) {
            $variant = $resolver();
            $name = $variant->variantName;

            if (isset($variants[$name])) {
                throw new InvalidArgumentException("Duplicate variant name: '{$name}'");
            }

            $variants[$name] = $variant;
        }

        return $variants;
    }

    public static function get(string $name): ?ImageVariant
    {
        foreach (static::$resolvers as $resolver) {
            $variant = $resolver();
            if ($variant->variantName === $name) {
                return $variant;
            }
        }

        return null;
    }

    public static function names(): array
    {
        return array_keys(self::all());
    }

    public static function clear(): void
    {
        static::$resolvers = [];
    }
}
