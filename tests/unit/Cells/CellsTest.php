<?php

use App\Cells\Html;
use App\Cells\PaginationCell;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * The view cells of the design system draw safe, accessible HTML.
 *
 * @internal
 */
final class CellsTest extends CIUnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        helper('hyper_ui');
        service('language')->setLocale('en');
    }

    public function testIconsDrawFromTheSpriteAndFallBackOnAnOddName(): void
    {
        $this->assertStringContainsString('assets/ui/icons.svg#plus', icon('plus'));
        $this->assertStringContainsString('aria-hidden="true"', icon('plus'));
        $this->assertStringContainsString('class="icon sm"', icon('plus', 'sm'));
        $this->assertStringContainsString('#doc', icon('"><script>'), 'An odd name cannot break out of the attribute.');
    }

    public function testAttributesAreEscapedAndOddNamesAreSkipped(): void
    {
        $html = Html::attrs(['hx-get' => '/a?b="c"', 'disabled' => true, 'hidden' => false, 'x' => null, 'bad name' => 'x', 'on"click' => 'y']);

        $this->assertSame(' hx-get="/a?b=&quot;c&quot;" disabled', $html);
    }

    public function testAButtonIsAButtonOrALinkWithTheRightClasses(): void
    {
        $button = ui('button', ['label' => 'Save <now>', 'variant' => 'primary', 'type' => 'submit', 'icon' => 'check']);
        $this->assertStringContainsString('<button class="btn primary" type="submit">', $button);
        $this->assertStringContainsString('Save &lt;now&gt;', $button);

        $link = ui('button', ['label' => 'New', 'href' => '/admin/new', 'variant' => 'danger', 'size' => 'small']);
        $this->assertStringContainsString('<a class="btn danger small" href="/admin/new">', $link);
        $this->assertStringNotContainsString('type=', $link);
    }

    public function testAnIconOnlyButtonKeepsItsNameForScreenReaders(): void
    {
        $html = ui('button', ['label' => 'Remove', 'icon' => 'trash', 'iconOnly' => true]);

        $this->assertStringContainsString('aria-label="Remove"', $html);
        $this->assertStringContainsString('icon-only', $html);
        $this->assertStringNotContainsString('<span>Remove</span>', $html);
    }

    public function testAFieldWiresTheLabelTheHelpAndTheError(): void
    {
        $html = ui('field', ['id' => 'email', 'label' => 'Email', 'kind' => 'email', 'value' => 'a"b@c.test', 'help' => 'We never share it.', 'error' => 'Enter an email address.', 'required' => true]);

        $this->assertStringContainsString('<label for="email">Email', $html);
        $this->assertStringContainsString('type="email"', $html);
        $this->assertStringContainsString('value="a&quot;b@c.test"', $html);
        $this->assertStringContainsString('aria-invalid="true"', $html);
        $this->assertStringContainsString('aria-describedby="email-help email-error"', $html);
        $this->assertStringContainsString('id="email-help"', $html);
        $this->assertStringContainsString('id="email-error"', $html);
        $this->assertStringContainsString('class="field invalid"', $html);
        $this->assertStringContainsString(' required', $html);
    }

    public function testAFieldDrawsATextareaAndASelectWithTheChoiceMarked(): void
    {
        $area = ui('field', ['id' => 'about', 'label' => 'About', 'kind' => 'textarea', 'value' => '<b>x</b>']);
        $this->assertStringContainsString('<textarea class="textarea"', $area);
        $this->assertStringContainsString('&lt;b&gt;x&lt;/b&gt;', $area);

        $select = ui('field', ['id' => 'kind', 'label' => 'Kind', 'kind' => 'select', 'value' => 'b', 'options' => ['a' => 'A', 'b' => 'B']]);
        $this->assertStringContainsString('<option value="b" selected>B</option>', $select);
        $this->assertStringNotContainsString('<option value="a" selected', $select);
    }

    public function testAFieldTakesAnInputFromTheCaller(): void
    {
        $html = ui('field', ['id' => 'cover', 'label' => 'Cover', 'input' => '<button id="cover" type="button">Pick</button>']);

        $this->assertStringContainsString('<button id="cover" type="button">Pick</button>', $html);
        $this->assertStringNotContainsString('<input', $html);
    }

    public function testPaginationLeavesOutTheMiddleAndMarksTheCurrentPage(): void
    {
        $cell = new PaginationCell();
        $cell->page = 6;
        $cell->perPage = 10;
        $cell->total = 120;

        $this->assertSame(12, $cell->pageCount());
        $this->assertSame([1, null, 5, 6, 7, null, 12], $cell->window());
        $this->assertSame(51, $cell->from());
        $this->assertSame(60, $cell->to());

        $html = ui('pagination', ['page' => 6, 'perPage' => 10, 'total' => 120, 'href' => '/list?page={page}']);
        $this->assertStringContainsString('aria-current="page">6</a>', $html);
        $this->assertStringContainsString('href="/list?page=5"', $html);
        $this->assertStringContainsString('Showing 51 to 60 of 120', $html);
        $this->assertStringNotContainsString('hx-get', $html);
    }

    public function testPaginationAddsHtmxWhenAskedAndHandlesTheEnds(): void
    {
        $html = ui('pagination', ['page' => 1, 'perPage' => 10, 'total' => 25, 'href' => '/l?page={page}', 'attrs' => ['hx-target' => '#rows']]);

        $this->assertStringContainsString('hx-get="/l?page=2"', $html);
        $this->assertStringContainsString('hx-target="#rows"', $html);
        $this->assertStringContainsString('<span aria-disabled="true">Previous</span>', $html);

        $one = ui('pagination', ['page' => 9, 'perPage' => 10, 'total' => 5]);
        $this->assertStringNotContainsString('class="pages"', $one, 'One page needs no links.');
        $this->assertStringContainsString('Showing 1 to 5 of 5', $one);

        $none = ui('pagination', ['total' => 0]);
        $this->assertStringContainsString('Nothing to show', $none);
    }

    public function testATableMarksTheSortedColumnAndKeepsCellsAsGiven(): void
    {
        $html = ui('table', [
            'caption'    => 'Articles',
            'selectable' => true,
            'columns'    => [
                ['key' => 'title', 'label' => 'Title', 'class' => 'label-cell', 'href' => '/l?sort=title', 'dir' => 'asc'],
                ['key' => 'views', 'label' => 'Views', 'class' => 'num'],
            ],
            'rows'       => [['id' => 7, 'label' => 'Hello <i>', 'cells' => ['title' => '<a href="/e/7">Hello</a>', 'views' => '12.5'], 'actions' => '']],
        ]);

        $this->assertStringContainsString('<caption class="visually-hidden">Articles</caption>', $html);
        $this->assertStringContainsString('aria-sort="ascending"', $html);
        $this->assertStringContainsString('href="/l?sort=title"', $html);
        $this->assertStringContainsString('<a href="/e/7">Hello</a>', $html);
        $this->assertStringContainsString('aria-label="Select Hello &lt;i&gt;"', $html);
        $this->assertStringContainsString('name="ids[]" value="7"', $html);
    }

    public function testAChipOffersARemoveLinkThatNamesWhatItRemoves(): void
    {
        $html = ui('chip', ['label' => 'Tags is News', 'tone' => 'tint', 'removeHref' => '/l', 'removeLabel' => 'Remove the Tags filter']);

        $this->assertStringContainsString('class="chip tint"', $html);
        $this->assertStringContainsString('aria-label="Remove the Tags filter"', $html);
    }

    public function testToastsPassTheFirstMessagesAsEscapedJson(): void
    {
        $html = ui('toasts', ['initial' => [['type' => 'ok', 'message' => 'Saved "now" <b>'], ['type' => 'evil', 'message' => 'x']]]);

        $this->assertStringContainsString('aria-live="polite"', $html);
        $this->assertStringNotContainsString('<b>', $html);
        $this->assertStringContainsString('data-initial="', $html);
        $this->assertStringContainsString('&quot;type&quot;:&quot;ok&quot;', $html);
        $this->assertStringNotContainsString('evil', $html, 'An unknown type becomes ok.');
    }

    public function testAnEmptyStateAsksForTheNextStep(): void
    {
        $html = ui('emptyState', ['title' => 'No articles yet', 'text' => 'Write the first one.', 'actionLabel' => 'New article', 'actionHref' => '/admin/model/1/new']);

        $this->assertStringContainsString('<h2>No articles yet</h2>', $html);
        $this->assertStringContainsString('href="/admin/model/1/new"', $html);
    }

    public function testTabsAndSegmentedChoicesMarkTheCurrentOne(): void
    {
        $tabs = ui('tabs', ['label' => 'Model', 'items' => [['label' => 'Fields', 'href' => '/f', 'current' => true], ['label' => 'Access', 'href' => '/a']]]);
        $this->assertStringContainsString('<a href="/f" aria-current="page">Fields</a>', $tabs);
        $this->assertStringNotContainsString('<a href="/a" aria-current', $tabs);

        $seg = ui('segmented', ['items' => [['label' => 'Live', 'href' => '/l', 'current' => true, 'count' => 23], ['label' => 'Trash', 'href' => '/t', 'count' => 2]]]);
        $this->assertStringContainsString('aria-current="true"', $seg);
        $this->assertStringContainsString('<span class="n">23</span>', $seg);
    }

    public function testTheFilterBarWorksWithoutScriptsAndAddsHtmxForATarget(): void
    {
        $plain = ui('filterBar', ['action' => '/list', 'searchLabel' => 'Search titles', 'q' => 'a"b']);
        $this->assertStringContainsString('method="get"', $plain);
        $this->assertStringContainsString('value="a&quot;b"', $plain);
        $this->assertStringNotContainsString('hx-get', $plain);

        $live = ui('filterBar', ['action' => '/list', 'target' => '#rows', 'searchLabel' => 'Search', 'applied' => [['label' => 'Tags is News', 'removeHref' => '/list']]]);
        $this->assertStringContainsString('hx-get="/list"', $live);
        $this->assertStringContainsString('hx-target="#rows"', $live);
        $this->assertStringContainsString('Tags is News', $live);
    }

    public function testAModalIsADialogWithTheConfirmHooks(): void
    {
        $html = ui('modal', ['id' => 'confirm-dialog', 'title' => 'Delete?', 'body' => '<p data-confirm-text></p>', 'danger' => true]);

        $this->assertStringContainsString('<dialog class="modal" id="confirm-dialog" aria-labelledby="confirm-dialog-title">', $html);
        $this->assertStringContainsString('data-confirm-ok', $html);
        $this->assertStringContainsString('danger solid', $html);
        $this->assertStringContainsString('>Cancel<', $html);
    }
}
