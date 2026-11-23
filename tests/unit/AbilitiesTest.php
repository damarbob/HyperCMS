<?php

use App\Data\Abilities;
use App\Data\Roles;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * The names of abilities and roles, and the words every ability of core has in both languages.
 *
 * @internal
 */
final class AbilitiesTest extends CIUnitTestCase
{
    public function testTheActionsOnAModelAreRecognisedByName(): void
    {
        foreach (Abilities::MODEL_ACTIONS as $action) {
            $this->assertTrue(Abilities::isModelAbility('entries.' . $action), $action);
        }
        $this->assertFalse(Abilities::isModelAbility('entries.browse'));
        $this->assertFalse(Abilities::isModelAbility('entries.purge'));
        $this->assertFalse(Abilities::isModelAbility('media.manage'));
    }

    public function testACustomAbilityNameIsTwoOrMoreLowercaseWordsJoinedByDots(): void
    {
        foreach (['shop.refund', 'shop.orders.export', 'a1.b_2'] as $name) {
            $this->assertSame(1, preg_match(Abilities::NAME, $name), $name);
        }
        foreach (['refund', 'Shop.refund', 'shop.', '.refund', 'shop refund', '1shop.refund', 'shop..refund', ''] as $name) {
            $this->assertSame(0, preg_match(Abilities::NAME, $name), $name);
        }
    }

    public function testARoleNameIsLowercaseWithDashesAndUnderscoresAndStartsWithALetter(): void
    {
        foreach (['editor', 'shop-manager', 'a1', 'night_shift'] as $name) {
            $this->assertSame(1, preg_match(Roles::NAME, $name), $name);
        }
        foreach (['Editor', '1st', 'a', 'has space', 'x' . str_repeat('y', 32), '', 'ünï'] as $name) {
            $this->assertSame(0, preg_match(Roles::NAME, $name), $name);
        }
    }

    public function testEveryAbilityOfCoreHasANameAndAnExplanationInBothLanguages(): void
    {
        foreach (['en', 'id'] as $locale) {
            $words = require APPPATH . "Language/$locale/Abilities.php";
            foreach (Abilities::CORE as $name) {
                $key = str_replace('.', '_', $name);
                $this->assertArrayHasKey($key, $words, "$locale $name");
                $this->assertArrayHasKey($key . '_text', $words, "$locale $name text");
                $this->assertNotSame('', $words[$key]);
            }
        }
    }
}
