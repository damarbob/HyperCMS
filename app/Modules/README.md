# The module contract

A module is a folder under `modules/` that adds something to HyperCMS without changing core. This page says what a module
may use, and everything a module may use is in `app/Modules/` or is a service named here. Anything else of core can change
without notice.

## A module

A module `Shop` lives in `modules/Shop/`. It is switched on by naming it in the list of active modules, and it has a class
`Shop\Module` that extends `App\Modules\Module`:

```php
namespace Shop;

use App\Modules\Ability;
use App\Modules\MenuEntry;
use App\Modules\Module as BaseModule;
use App\Modules\ModuleRoutes;

final class Module extends BaseModule
{
    public function title(): string { return lang('Shop.moduleName'); }

    public function abilities(): array
    {
        return [new Ability('shop.refund', lang('Shop.refund'), lang('Shop.refundText'))];
    }

    public function menu(): array
    {
        return [new MenuEntry('shop', lang('Shop.moduleName'), base_url('admin/shop'), 'bolt', 'shop.refund')];
    }

    public function routes(ModuleRoutes $routes): void
    {
        $routes->admin('shop.refund', static function ($routes) {
            $routes->get('shop', 'Orders::index');
        });
    }
}
```

Core calls the methods of the class and nothing else of the module. A module that has no `Module` class still loads its
`init.php` as before, so a module can move to the contract one piece at a time.

## What a module can do

| A module can | Through | Notes |
| :-- | :-- | :-- |
| Say which core it works with | `requires()` | A comparison such as `>=0.2.0`, or `*`. A module that does not fit is not loaded, and `service('modules')->skipped()` says why. A pre-release counts as its release |
| Add abilities | `abilities()` | Names are two or more lowercase words joined by dots. A name that core uses, or one of the model actions (`entries.read` and its four siblings), keeps the module out. A role is given them on the Roles screen |
| Add admin pages and a menu entry | `routes()` and `menu()` | `$routes->admin('shop.refund', ...)` adds the sign in check and the ability check. Controllers are looked up in the `Controllers` folder of the module's own namespace |
| Add pages for visitors | `$routes->site(...)` | No sign in. The module checks what it needs |
| Add a settings screen | `settings()` | A `SettingsScreen` appears in the settings menu, for the roles that have its ability |
| Add a field type | `fieldTypes()` | Classes that implement `App\Fields\FieldType`, which the model builder offers at once because each type lists its own options |
| Declare models it needs | `blueprints()` | A `Blueprint` states the fields a model must have, and may accept other types for a field. `App\Modules\Blueprints` finds the models that fit and makes one, a module's settings screen offers both, and the choice is kept in the settings. The module reads it with `service('modules')->modelOf('Shop', 'page')`, so a module never scans the models |
| Put content into core pages | `slots()` | Named places with a typed payload, below |
| Read and write data | `service('entryStore')`, `service('modelStore')`, `service('files')`, `service('settings')` and `can()` | `files` is the media library, described under Files below |
| Draw like core | `ui('button', ...)`, `ui('table', ...)` and the other cells of the design system | The cells are documented on the design page, `admin/design`, outside production |

## Controllers

A controller of a module extends `App\Modules\ModuleController`, never `AdminController`, and imports nothing from
`App\Controllers`. The base class gives a page the layout data and the menu, and has `page($view, $data)`,
`allowed($ability, $modelId)`, `json($payload, $status)` and `leave($address, $message, $type)`.

A module that changes who may do what must hold itself to the same rule as core: a person may hand out only what they have
themselves. `service('permissions')->mayAssignRole($actor, $role)` and `mayManageUser($actor, $user)` answer it, and the
Users screen uses them.

## Files

The media library keeps the files of the site outside the public folder, in `writable/uploads` unless `media.uploadPath` says
otherwise, and every file is a row of `hyper_files`. A file is known by its id, so a link survives a rename or a move.

| A module can | Through |
| :-- | :-- |
| Find a file | `service('files')->find($id)` and `findMany($ids)`, which return the row or nothing |
| Link to a file | `App\Data\Files::url($file)` for a page of the admin, and `'/' . Files::path($file)` for text that is stored, which is `/media/{id}/{name}` |
| Add a file | `service('files')->store($tmpPath, $name, $folder, $userId)`, which refuses a name that a server could run |
| Ask the person for a file | `window.HyperMedia.pick({accept: 'image', multiple: false})` in a page that loads `assets/ui/media.js`, which resolves with the chosen files |
| Hold a file in an entry | A field of type `file`, which stores the id, or a list of ids |

A page that must show the library inside a frame can open `admin/media/frame?requester_id={hex}`. It posts the links of the
chosen files to its parent window as `filesSelected_r{requester_id}`.

## Slots

A slot is a named place in a core page. Core gives every module that asked for the slot one payload object, and the module
changes it through the methods of that class. A slot takes only its own payload class, and a name that is not a slot throws.

| Slot | Payload | What a module does with it |
| :-- | :-- | :-- |
| `Slots::ENTRY_FORM_TOOLBAR` | `Slots\EntryFormToolbar` | `link($label, $href, $icon)` adds a button beside the save button of the entry form |
| `Slots::MODEL_LIST_NEW` | `Slots\ModelListNew` | `pointTo($url)` sends the New button of a model's list to the module's own screen |

```php
public function slots(Slots $slots): void
{
    $slots->on(Slots::ENTRY_FORM_TOOLBAR, static function (EntryFormToolbar $toolbar): void {
        $toolbar->link('Open editor', base_url('admin/editor/' . $toolbar->entryId));
    });
}
```

## What changes without notice

Everything outside `app/Modules/`, the services above, `can()` and the cells of the design system: controllers, libraries,
views, the layout, the table names and the hooks with raw arrays (`hook('Backend.controller:...')`). The old hooks still work
for modules that have not moved, and they are removed when the last of those modules moves.
