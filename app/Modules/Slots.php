<?php

namespace App\Modules;

use App\Modules\Slots\EntryFormToolbar;
use App\Modules\Slots\ModelListNew;
use InvalidArgumentException;

/**
 * The named places of core pages that a module can put content into. Each slot has one payload class, an object that
 * core hands to every module that asked for the slot, and the module changes it through the methods of that class.
 * This replaces hooks with names and raw arrays, because a payload says what a module may do with a slot and a wrong
 * use fails at once.
 */
final class Slots
{
    /**
     * Buttons beside the save button of the entry form.
     */
    public const ENTRY_FORM_TOOLBAR = 'entry.form.toolbar';

    /**
     * The address of the New button of a model's list, which a module may point at its own screen.
     */
    public const MODEL_LIST_NEW = 'model.list.new';

    /**
     * The payload class of each slot.
     */
    private const PAYLOADS = [
        self::ENTRY_FORM_TOOLBAR => EntryFormToolbar::class,
        self::MODEL_LIST_NEW     => ModelListNew::class,
    ];

    /**
     * @var array<string, list<callable>>
     */
    private array $fillers = [];

    /**
     * Ask to be called when a page fills a slot.
     *
     * @param callable(object): void $filler Receives the payload of the slot.
     *
     * @throws InvalidArgumentException When there is no such slot.
     */
    public function on(string $slot, callable $filler): void
    {
        if (! isset(self::PAYLOADS[$slot])) {
            throw new InvalidArgumentException("There is no slot '$slot'.");
        }

        $this->fillers[$slot][] = $filler;
    }

    /**
     * Hand the payload to every module that asked for the slot, and return it.
     *
     * @template T of object
     *
     * @param T $payload
     *
     * @return T
     *
     * @throws InvalidArgumentException When there is no such slot or the payload is not the class of the slot.
     */
    public function fill(string $slot, object $payload): object
    {
        $class = self::PAYLOADS[$slot] ?? throw new InvalidArgumentException("There is no slot '$slot'.");
        if (! $payload instanceof $class) {
            throw new InvalidArgumentException("The slot '$slot' takes a $class.");
        }

        foreach ($this->fillers[$slot] ?? [] as $filler) {
            $filler($payload);
        }

        return $payload;
    }

    /**
     * @return list<string>
     */
    public static function names(): array
    {
        return array_keys(self::PAYLOADS);
    }
}
