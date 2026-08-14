# Adding a module

A worked example: a `Project` module with CRUD, a data table, and its own sidebar entry.

## 1. Scaffold the folders

```
app/Modules/Project/
  ProjectServiceProvider.php
  Models/Project.php
  Enums/ProjectStatus.php
  DTOs/ProjectData.php
  Actions/{CreateProject,UpdateProject,DeleteProject}.php
  Http/Controllers/ProjectController.php
  Http/Requests/{StoreProjectRequest,UpdateProjectRequest}.php
  Http/Resources/ProjectResource.php
  Policies/ProjectPolicy.php
  Database/Migrations/2026_08_01_000000_create_projects_table.php
  Routes/web.php
```

Nothing needs registering anywhere. `ModuleRegistryServiceProvider` finds `ProjectServiceProvider` because of its name and location.

## 2. The provider

```php
final class ProjectServiceProvider extends ModuleServiceProvider
{
    protected array $policies = [Project::class => ProjectPolicy::class];

    protected function bootModule(): void
    {
        $this->app->make(NavigationBuilder::class)->register(
            NavigationSection::make('Work', 40)->items([
                NavigationItem::make('Projects', 'projects.index')
                    ->icon('folder-kanban')
                    ->permissions('projects.view')
                    ->activeWhen('projects.*'),
            ]),
        );
    }
}
```

The base class has already loaded `Routes/web.php`, `Routes/api.php`, `Database/Migrations`, `Resources/lang` and the policies above.

## 3. Declare the permissions

Add a group to `config/permissions.php` and grant it to the roles that should have it:

```php
'projects' => [
    'label' => 'Projects',
    'permissions' => [
        'projects.view'   => 'View projects',
        'projects.create' => 'Create projects',
        'projects.update' => 'Edit projects',
        'projects.delete' => 'Delete projects',
    ],
],
```

Then `php artisan permission:sync`. A permission that is not in this file does not exist and will always deny.

## 4. Make the model tenant-owned

```php
final class Project extends Model
{
    use BelongsToCompany, HasFactory, SoftDeletes;
}
```

The migration needs `$table->foreignId('company_id')->constrained()->cascadeOnDelete();`. `BelongsToCompany` supplies the global scope and stamps `company_id` on create, so no Action ever sets it by hand.

## 5. The index screen

```php
public function index(Request $request): Response
{
    $this->authorize('viewAny', Project::class);

    return Inertia::render('projects/index', [
        'table' => TableBuilder::for(Project::query()->with('owner'), $request)
            ->columns([
                Column::make('name')->sortable()->searchable()->locked(),
                Column::make('status')->sortable(),
                Column::make('owner.name', 'Owner')->searchable('owner.name'),
                Column::make('created_at', 'Created')->sortable(),
            ])
            ->filters([
                Filter::make('status')->fromEnum(ProjectStatus::class)->multiple(),
                Filter::make('created_at', 'Created')->dateRange(),
            ])
            ->defaultSort('created_at')
            ->transform(fn (Project $p): array => (new ProjectResource($p))->resolve())
            ->toArray(),
    ]);
}
```

Sorting, searching and filtering are whitelisted by those `Column` and `Filter` declarations — a crafted query string cannot reach an undeclared column.

## 6. The page

`resources/js/pages/projects/index.tsx`:

```tsx
export default function ProjectsIndex({ table }: { table: TablePayload<ProjectRow> }) {
    return (
        <AppLayout title="Projects" breadcrumbs={[{ label: 'Projects' }]}>
            <PageHeader title="Projects" action={<Button>New project</Button>} />
            <DataTable payload={table} cells={{ status: (row) => <Badge>{row.status}</Badge> }} />
        </AppLayout>
    );
}
```

`DataTable` handles URL state, partial reloads, selection, bulk actions and exports. You supply cell renderers for the columns that need more than text.

## 7. Tests

`tests/Feature/Project/ProjectControllerTest.php`. The helpers in `tests/Pest.php` give you a workspace and a permission-scoped member in one line:

```php
it('denies a member without the permission', function () {
    $company = workspace();
    actingAsMember(memberWith([], $company), $company)
        ->get(route('projects.index'))
        ->assertForbidden();
});

it('does not leak projects from another workspace', function () { … });
```

Every controller needs, at minimum: unauthenticated → redirect, authenticated-without-permission → 403, happy path, validation failure, and a tenant-isolation test.

## Checklist

- [ ] `declare(strict_types=1);` at the top of every PHP file
- [ ] FormRequest for validation, Policy for authorisation — never an ad-hoc `if`
- [ ] Business logic in an Action, not the controller
- [ ] Permissions declared in `config/permissions.php`
- [ ] Tunables in `config/saas.php`, not inline
- [ ] Resource shape mirrored in `resources/js/types`
- [ ] Loading, empty and error states on every screen
- [ ] `composer lint`, `composer test`, `npm run types`, `npm run lint`, `npm test` all green
