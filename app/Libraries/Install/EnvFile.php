<?php

namespace App\Libraries\Install;

use InvalidArgumentException;

/**
 * Writes the `.env` file the install page makes. Every value is put in double quotes, with the characters CodeIgniter's
 * reader treats as special escaped, so a database password with a `#`, a `$` or a quote reads back as typed.
 */
final class EnvFile
{
    /**
     * @param array<string, string> $values The settings by name, such as `database.default.password`.
     * @param list<string>          $notes  Comment lines to put at the top.
     */
    public static function render(array $values, array $notes = []): string
    {
        $lines = [];
        foreach ($notes as $note) {
            $lines[] = '# ' . $note;
        }
        if ($notes !== []) {
            $lines[] = '';
        }

        foreach ($values as $name => $value) {
            if (preg_match('/^[A-Za-z0-9_.]+$/', $name) !== 1) {
                throw new InvalidArgumentException("The setting name '$name' is not valid.");
            }
            $lines[] = $name . ' = ' . self::quote($value);
        }

        return implode("\n", $lines) . "\n";
    }

    /**
     * Whether a value can be written and read back unchanged. A line break would end the setting early, a null byte
     * cannot be kept in the environment, and the reader replaces `${NAME}` with the variable of that name.
     */
    public static function writable(string $value): bool
    {
        return preg_match('/[\r\n\0]|\$\{[A-Za-z0-9_.]+\}/', $value) !== 1;
    }

    private static function quote(string $value): string
    {
        if (! self::writable($value)) {
            throw new InvalidArgumentException('A setting cannot hold a line break, a null byte or the text ${NAME}.');
        }

        return '"' . str_replace(['\\', '"'], ['\\\\', '\\"'], $value) . '"';
    }
}
