<?php

use App\Data\Abilities;
use App\Fields\FieldType;
use App\Modules\Ability;
use App\Modules\Blueprint;
use App\Modules\MenuEntry;
use App\Modules\Module;
use App\Modules\Modules;
use App\Modules\SettingsScreen;
use App\Modules\Slots;
use App\Modules\Slots\EntryFormToolbar;
use App\Modules\Slots\ModelListNew;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * The module contract: which modules load, what core takes from them, and how a slot hands out its payload.
 *
 * @internal
 */
final class ModulesTest extends CIUnitTestCase
{
    private function registry(string $version = '0.2.0'): Modules
    {
        return new Modules($this->createMock(Abilities::class), new Slots(), service('settings'), $version);
    }

    private function module(string $requires = '*', array $abilities = [], array $slotsUsed = []): Module
    {
        return new class ($requires, $abilities, $slotsUsed, $this->createMock(FieldType::class)) extends Module {
            public function __construct(private string $need, private array $own, private array $used, private FieldType $type)
            {
            }

            public function title(): string
            {
                return 'Probe';
            }

            public function requires(): string
            {
                return $this->need;
            }

            public function abilities(): array
            {
                return $this->own;
            }

            public function menu(): array
            {
                return [new MenuEntry('probe', 'Probe', 'http://x/probe', 'doc', 'probe.use')];
            }

            public function settings(): array
            {
                return [new SettingsScreen('probe-settings', 'Probe settings', 'http://x/settings/probe', 'probe.use')];
            }

            public function fieldTypes(): array
            {
                return [$this->type];
            }

            public function blueprints(): array
            {
                return [new Blueprint('page', 'Page', 'The pages.', [['id' => 'path', 'type' => 'text']])];
            }

            public function slots(Slots $slots): void
            {
                foreach ($this->used as $slot => $filler) {
                    $slots->on($slot, $filler);
                }
            }
        };
    }

    public function testAVersionIsCheckedAgainstWhatAModuleAsksFor(): void
    {
        $this->assertTrue(Modules::satisfies('0.2.0', '>=0.2.0'));
        $this->assertTrue(Modules::satisfies('0.2.0-beta.1', '>=0.2.0'), 'A pre-release counts as its release.');
        $this->assertTrue(Modules::satisfies('0.1.0-alpha.9', '>=0.1.0'));
        $this->assertFalse(Modules::satisfies('0.1.0-alpha.9', '>=0.2.0'));
        $this->assertTrue(Modules::satisfies('1.4.0', '*'));
        $this->assertTrue(Modules::satisfies('1.4.0', ''));
        $this->assertFalse(Modules::satisfies('1.4.0', '<1.0.0'));
        $this->assertFalse(Modules::satisfies('1.4.0', 'nonsense'));
    }

    public function testAModuleThatAsksForANewerCoreIsLeftOutAndSaysWhy(): void
    {
        $registry = $this->registry('0.1.0');
        $registry->register($this->module('>=0.2.0'));

        $this->assertSame([], $registry->all());
        $this->assertCount(1, $registry->skipped());
        $this->assertStringContainsString('>=0.2.0', array_values($registry->skipped())[0]);
    }

    public function testALoadedModuleOffersItsMenuSettingsFieldTypesAndBlueprints(): void
    {
        $registry = $this->registry();
        $registry->register($this->module('>=0.1.0'));

        $menu = $registry->menu();
        $this->assertArrayHasKey('probe', array_values($menu)[0]);
        $this->assertSame('probe.use', array_values($menu)[0]['probe']['ability']);
        $this->assertSame('http://x/settings/probe', array_values($menu)[0]['settings']['submenu']['probe-settings']['url']);

        $this->assertCount(1, $registry->fieldTypes());
        $this->assertInstanceOf(FieldType::class, $registry->fieldTypes()[0]);
        $this->assertSame('page', $registry->blueprints()[0]['blueprint']->key);
    }

    public function testABlueprintFitsAModelThatHasItsFieldsWithTheSameTypes(): void
    {
        $blueprint = new Blueprint('page', 'Page', '', [['id' => 'path', 'type' => 'text'], ['id' => 'html', 'type' => 'code']]);

        $this->assertTrue($blueprint->fits([['id' => 'path', 'type' => 'text'], ['id' => 'html', 'type' => 'code'], ['id' => 'extra', 'type' => 'number']]));
        $this->assertFalse($blueprint->fits([['id' => 'path', 'type' => 'text']]));
        $this->assertFalse($blueprint->fits([['id' => 'path', 'type' => 'longtext'], ['id' => 'html', 'type' => 'code']]));
    }

    public function testAnAbilityThatClashesWithCoreOrIsBadlyNamedKeepsTheModuleOut(): void
    {
        foreach (['media.manage', 'entries.read', 'single'] as $name) {
            $registry = $this->registry();
            $registry->boot([]);
            try {
                $registry->register($this->module('*', [new Ability($name, 'x')]));
                $this->fail("$name should be refused");
            } catch (InvalidArgumentException $e) {
                $this->assertStringContainsString($name, $e->getMessage());
            }
        }
    }

    public function testASlotHandsItsPayloadToEveryModuleThatAskedAndRefusesOthers(): void
    {
        $slots = new Slots();
        $slots->on(Slots::ENTRY_FORM_TOOLBAR, static function (EntryFormToolbar $toolbar): void {
            $toolbar->link('Open editor', 'http://x/editor/' . $toolbar->entryId);
        });
        $slots->on(Slots::MODEL_LIST_NEW, static function (ModelListNew $new): void {
            $new->pointTo('http://x/new-page');
        });

        $toolbar = $slots->fill(Slots::ENTRY_FORM_TOOLBAR, new EntryFormToolbar(1, 7));
        $this->assertFalse($toolbar->isNew());
        $this->assertSame('http://x/new-page', $slots->fill(Slots::MODEL_LIST_NEW, new ModelListNew(1, 'http://x/old'))->url());

        try {
            $slots->fill(Slots::MODEL_LIST_NEW, new EntryFormToolbar(1, 1));
            $this->fail('A slot takes only its own payload.');
        } catch (InvalidArgumentException) {
            $this->assertTrue(true);
        }

        $this->expectException(InvalidArgumentException::class);
        $slots->on('no.such.slot', static fn () => null);
    }

    public function testASlotWithNobodyAskingLeavesThePayloadAsItWas(): void
    {
        $slots = new Slots();

        $this->assertSame('http://x/old', $slots->fill(Slots::MODEL_LIST_NEW, new ModelListNew(1, 'http://x/old'))->url());
        $this->assertSame('', $slots->fill(Slots::ENTRY_FORM_TOOLBAR, new EntryFormToolbar(1, 0))->html());
        $this->assertTrue((new EntryFormToolbar(1, 0))->isNew());
    }
}
