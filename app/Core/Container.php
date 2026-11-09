<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Conteneur de services explicite : tout service avec dépendances est déclaré à la main
 * (pas d'autowiring). Seules les classes sans constructeur sont instanciées automatiquement.
 */
final class Container
{
    /** @var array<string,callable> */
    private array $factories = [];
    /** @var array<string,bool> */
    private array $shared = [];
    /** @var array<string,object> */
    private array $instances = [];

    public function singleton(string $id, callable $factory): void
    {
        $this->factories[$id] = $factory;
        $this->shared[$id] = true;
        unset($this->instances[$id]);
    }

    public function bind(string $id, callable $factory): void
    {
        $this->factories[$id] = $factory;
        $this->shared[$id] = false;
        unset($this->instances[$id]);
    }

    public function instance(string $id, object $object): void
    {
        $this->instances[$id] = $object;
    }

    /**
     * @template T of object
     * @param class-string<T> $id
     * @return T
     */
    public function make(string $id): object
    {
        if (isset($this->instances[$id])) {
            /** @var T */
            return $this->instances[$id];
        }
        if (isset($this->factories[$id])) {
            $object = ($this->factories[$id])($this);
            if ($this->shared[$id]) {
                $this->instances[$id] = $object;
            }
            /** @var T */
            return $object;
        }
        if (class_exists($id)) {
            $constructor = (new \ReflectionClass($id))->getConstructor();
            if ($constructor === null || $constructor->getNumberOfParameters() === 0) {
                return new $id();
            }
        }

        throw new \LogicException("Service non enregistré : {$id}");
    }
}
