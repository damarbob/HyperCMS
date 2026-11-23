<?php

use App\Modules\Slots;
use App\Modules\Slots\DashboardPanels;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * The dashboard slot: which panels a module may add and what core does with a panel that cannot be shown.
 *
 * @internal
 */
final class DashboardPanelsTest extends CIUnitTestCase
{
    private function panels(array $abilities = []): DashboardPanels
    {
        return new DashboardPanels(static fn (string $ability): bool => in_array($ability, $abilities, true));
    }

    public function testAPanelWithAnAbilityIsKeptOnlyForAPersonWhoHoldsIt(): void
    {
        $panels = $this->panels(['shop.orders']);
        $panels->panel('Orders', 'Shop\Views\orders', [], 'shop.orders', DashboardPanels::MAIN);
        $panels->panel('Refunds', 'Shop\Views\refunds', [], 'shop.refunds', DashboardPanels::MAIN);
        $panels->panel('Open to all', 'Shop\Views\news');

        $this->assertSame(1, $panels->count(DashboardPanels::MAIN));
        $this->assertSame(1, $panels->count(DashboardPanels::SIDE));
    }

    public function testAColumnThatDoesNotExistAndAnEmptyTitleAreRefusedAtOnce(): void
    {
        $panels = $this->panels();

        foreach ([['Title', 'Shop\Views\x', 'middle'], ['', 'Shop\Views\x', 'side'], ['Title', '', 'side']] as [$title, $view, $column]) {
            try {
                $panels->panel($title, $view, [], '', $column);
                $this->fail('A bad panel should be refused.');
            } catch (InvalidArgumentException) {
                $this->assertTrue(true);
            }
        }

        $this->assertSame(0, $panels->count(DashboardPanels::SIDE));
    }

    public function testAPanelWhoseViewFailsIsLeftOutAndTheOthersStillDraw(): void
    {
        $panels = $this->panels();
        $panels->panel('Broken', 'Shop\Views\does_not_exist', [], '', DashboardPanels::SIDE);
        $panels->panel('Works', 'App\Views\errors\html\error_404', ['message' => 'x'], '', DashboardPanels::SIDE);

        $level = ob_get_level();
        $html  = $panels->html(DashboardPanels::SIDE);

        $this->assertSame($level, ob_get_level(), 'A failed view must not leave an output buffer open.');
        $this->assertStringNotContainsString('Broken', $html);
        $this->assertStringContainsString('<h2 id="dp-1">Works</h2>', $html);
    }

    public function testAPanelTitleIsEscaped(): void
    {
        $panels = $this->panels();
        $panels->panel('<script>x</script>', 'App\Views\errors\html\error_404', ['message' => 'x']);

        $this->assertStringNotContainsString('<script>', $panels->html(DashboardPanels::SIDE));
    }

    public function testTheSlotIsRegisteredWithItsOwnPayload(): void
    {
        $slots = new Slots();
        $slots->on(Slots::DASHBOARD_PANELS, static function (DashboardPanels $panels): void {
            $panels->panel('One', 'x\y');
        });

        $panels = $slots->fill(Slots::DASHBOARD_PANELS, $this->panels());
        $this->assertSame(1, $panels->count(DashboardPanels::SIDE));
        $this->assertContains(Slots::DASHBOARD_PANELS, Slots::names());
    }
}
