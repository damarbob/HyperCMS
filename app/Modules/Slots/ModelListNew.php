<?php

namespace App\Modules\Slots;

/**
 * The payload of the New button of a model's list. A module whose model is made in its own screen, such as a page, points
 * the button there.
 */
final class ModelListNew
{
    private string $url;

    public function __construct(public readonly int $modelId, string $url)
    {
        $this->url = $url;
    }

    public function url(): string
    {
        return $this->url;
    }

    /**
     * Point the button at another address.
     */
    public function pointTo(string $url): void
    {
        $this->url = $url;
    }
}
