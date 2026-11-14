<?php

namespace App\Data;

use CodeIgniter\Settings\Settings;
use CodeIgniter\Shield\Entities\User;

/**
 * The one place that decides who may read the entries of a model through the API.
 *
 * A model's `user_groups` names the groups that may read it, and an empty list means every
 * signed-in user. A model marked public can also be read by a visitor who is not signed in.
 */
class ModelAccess
{
    /**
     * The setting that holds the ids of the public models.
     */
    private const SETTING = 'Hyper.publicModels';

    /**
     * Each model's groups by model id, read once per request.
     *
     * @var array<int, list<string>>|null
     */
    private ?array $groups = null;

    public function __construct(
        private ModelStore $models,
        private Settings $settings,
    ) {
    }

    /**
     * @return list<int>
     */
    public function publicIds(): array
    {
        $ids = $this->settings->get(self::SETTING);

        return is_array($ids) ? array_values(array_map('intval', $ids)) : [];
    }

    public function isPublic(int|string $modelId): bool
    {
        return in_array((int) $modelId, $this->publicIds(), true);
    }

    /**
     * Mark a model public or take the mark away. Nothing is written when the mark already matches.
     */
    public function setPublic(int $modelId, bool $public): void
    {
        if ($public === $this->isPublic($modelId)) {
            return;
        }

        $ids = array_values(array_diff($this->publicIds(), [$modelId]));
        if ($public) {
            $ids[] = $modelId;
            sort($ids);
        }

        $this->settings->set(self::SETTING, $ids);
    }

    /**
     * Drop the marks of models that no longer exist, so a later model that gets the same id
     * does not start out public.
     */
    public function forgetMissing(): void
    {
        $this->groups = null;
        $kept = array_values(array_intersect($this->publicIds(), array_keys($this->groups())));

        if ($kept !== $this->publicIds()) {
            $this->settings->set(self::SETTING, $kept);
        }
    }

    /**
     * Tell whether this user may read the entries of this model. Pass null for a visitor who is not signed in.
     *
     * A signed-in user gets true for an id that names no model, so the caller's own not-found answer still applies.
     */
    public function canRead(?User $user, int|string $modelId): bool
    {
        if ($this->isPublic($modelId)) {
            return true;
        }

        if ($user === null) {
            return false;
        }

        $groups = $this->groups()[(int) $modelId] ?? [];

        return $groups === [] || array_intersect($groups, $user->getGroups()) !== [];
    }

    /**
     * List the ids of the models this user may not read, for leaving their entries out of a list.
     *
     * @return list<int>
     */
    public function hiddenIds(User $user): array
    {
        $hidden = [];
        foreach (array_keys($this->groups()) as $modelId) {
            if (! $this->canRead($user, $modelId)) {
                $hidden[] = $modelId;
            }
        }

        return $hidden;
    }

    /**
     * @return array<int, list<string>>
     */
    private function groups(): array
    {
        return $this->groups ??= $this->models->userGroups();
    }
}
