<?php

use App\Libraries\RailMenu;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * What the rail draws from the menu the admin controller builds.
 *
 * @internal
 */
final class RailMenuTest extends CIUnitTestCase
{
    /**
     * @return array<string, array<string, array<string, mixed>>>
     */
    private function menu(): array
    {
        return [
            'General' => ['dashboard' => ['url' => 'http://x.test/admin/dashboard', 'text' => 'Dashboard', 'icon' => 'fa-solid fa-house']],
            'Data'    => [
                'models'  => ['url' => 'http://x.test/admin/models', 'text' => 'Models', 'icon' => 'fa-solid fa-circle-nodes'],
                'entries' => ['url' => 'http://x.test/admin/entries', 'text' => 'Entries', 'icon' => 'fa-solid fa-table-list'],
            ],
            'CONTENT' => [
                'model-1' => ['url' => 'http://x.test/admin/model/1', 'text' => 'Article', 'icon' => 'file-text', 'count' => 23],
                'model-2' => ['url' => 'http://x.test/admin/model/2', 'text' => '<b>Product</b>', 'icon' => 'fa-solid fa-unknown-thing'],
            ],
            'Others'  => [
                'settings' => [
                    'url'     => 'http://x.test/admin/settings',
                    'text'    => 'Settings',
                    'icon'    => 'fa-solid fa-cog',
                    'submenu' => [
                        'general' => ['url' => 'http://x.test/admin/settings', 'text' => 'General'],
                        'models'  => ['url' => 'http://x.test/admin/settings/models', 'text' => 'Models'],
                    ],
                ],
            ],
        ];
    }

    public function testIconsOfTheOldMenuMapToTheSprite(): void
    {
        $this->assertSame('dashboard', RailMenu::icon('fa-solid fa-house'));
        $this->assertSame('models', RailMenu::icon('fa-solid fa-circle-nodes'));
        $this->assertSame('settings', RailMenu::icon('fa-solid fa-cog'));
        $this->assertSame('doc', RailMenu::icon('file-text'));
        $this->assertSame('doc', RailMenu::icon('fa-solid fa-never-heard-of-it'));
        $this->assertSame('doc', RailMenu::icon(''));
        $this->assertSame('media', RailMenu::icon('media'), 'A name of the sprite is kept.');
    }

    public function testTheFirstGroupHasNoHeadingAndOthersAreInSentenceCase(): void
    {
        $groups = RailMenu::prepare($this->menu(), '/admin/dashboard');

        $this->assertSame(['', 'Data', 'Content', 'Others'], array_column($groups, 'label'));

        $ai = RailMenu::prepare(['General' => ['a' => ['url' => '/admin/a', 'text' => 'A']], 'AI' => ['b' => ['url' => '/admin/b', 'text' => 'B']], 'CATALOGUE' => ['c' => ['url' => '/admin/c', 'text' => 'C']]], '/');
        $this->assertSame(['', 'AI', 'Catalogue'], array_column($ai, 'label'), 'A short capital word is an acronym.');
    }

    public function testTheCurrentItemIsTheLongestMatchSoModelsDoesNotLightUpOnAModel(): void
    {
        $groups = RailMenu::prepare($this->menu(), '/admin/model/1/5/edit');
        $items  = array_merge(...array_column($groups, 'items'));
        $byText = array_column($items, 'current', 'text');

        $this->assertTrue($byText['Article']);
        $this->assertFalse($byText['Models']);
        $this->assertFalse($byText['Entries']);
        $this->assertFalse($byText['Dashboard']);
    }

    public function testASubmenuOpensWhenItsParentOrAChildIsCurrent(): void
    {
        $settings = fn (string $path): array => RailMenu::prepare($this->menu(), $path)[3]['items'][0];

        $this->assertFalse($settings('/admin/dashboard')['open']);
        $this->assertTrue($settings('/admin/settings')['open']);

        $child = $settings('/admin/settings/models');
        $this->assertTrue($child['open']);
        $this->assertSame([false, true], array_column($child['children'], 'current'));
    }

    public function testTextIsStrippedOfMarkupAndCountsPass(): void
    {
        $items = RailMenu::prepare($this->menu(), '/')[2]['items'];

        $this->assertSame(23, $items[0]['count']);
        $this->assertNull($items[1]['count']);
        $this->assertSame('Product', $items[1]['text']);
    }

    public function testAGroupWithNothingDrawableIsLeftOut(): void
    {
        $groups = RailMenu::prepare(['Empty' => [], 'Odd' => ['x' => ['text' => 'No url']], 'Real' => ['a' => ['url' => '/admin/a', 'text' => 'A']]], '/admin/a');

        $this->assertCount(1, $groups);
        $this->assertSame('', $groups[0]['label'], 'The first group drawn gets no heading.');
        $this->assertTrue($groups[0]['items'][0]['current']);
    }

    public function testNothingIsCurrentOffTheMenu(): void
    {
        $items = array_merge(...array_column(RailMenu::prepare($this->menu(), '/admin/profile'), 'items'));

        $this->assertSame([], array_filter(array_column($items, 'current')));
    }
}
