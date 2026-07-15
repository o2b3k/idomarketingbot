# Architecture: Structured Modules (Technical Layers)

## Overview

`idomarketing` uses **Structured Modules** — a lightweight, domain-aware modular architecture layered on top of Laravel's conventions. Each feature area (e.g. `Campaigns`, `Audiences`, `Analytics`) becomes a self-contained module with its own controllers, application services, repositories, and rich Eloquent models. Business rules live inside the models; services only orchestrate use cases.

This pattern was chosen because the project is a single-deployment Laravel monolith that is still at the foundation stage (only auth/settings/dashboard exist). Structured Modules gives new features a clear home as they grow, keeps velocity high by staying Laravel-idiomatic, and leaves a trivial migration path to Explicit Architecture if the domain later demands strict boundaries.

## Decision Rationale

- **Project type:** Marketing web application on the Laravel React Starter Kit (Inertia SPA), foundation stage.
- **Tech stack:** PHP 8.4 / Laravel 13 backend, React 19 + TypeScript (Inertia v3) frontend, Eloquent + SQLite.
- **Key factor:** Feature areas will accumulate over time; a growing app needs structure without the overhead of full Explicit Architecture. Single deployment rules out Microservices; the domain is not complex enough yet to justify Explicit Architecture.

## Folder Structure

Extend the existing Laravel layout — do **not** rewrite it. Existing framework-level auth/settings scaffolding stays where it is; **new feature areas** go under `app/Modules/`.

```text
app/
├── Modules/                              # ── FEATURE MODULES (new work goes here) ──
│   └── [Module]/                         # e.g. Campaigns, Audiences, Analytics
│       ├── Controllers/                  # Inertia/HTTP handlers — thin presentation
│       │   └── [Feature]Controller.php
│       ├── Services/                     # Application Services (use-case orchestration, NO domain logic)
│       │   └── [Feature]Service.php
│       ├── Repositories/                 # Data access: interface + Eloquent implementation
│       │   ├── [Entity]Repository.php            # interface
│       │   └── Eloquent[Entity]Repository.php    # implementation
│       ├── Models/                       # Rich Eloquent domain models (business rules live here)
│       │   └── [Entity].php
│       ├── Requests/                     # Form Request validation for this module
│       ├── Data/                         # DTOs / value objects (optional)
│       └── routes.php                    # Module routes (loaded from a service provider or RouteServiceProvider)
│
├── Http/
│   ├── Controllers/Controller.php        # Base controller
│   └── Middleware/                       # Cross-cutting: HandleInertiaRequests, HandleAppearance
├── Actions/Fortify/                      # Auth actions (framework-adjacent — leave in place)
├── Concerns/                             # Shared traits (validation rules, etc.)
├── Models/                               # Framework-level models (User) until owned by a module
├── Providers/                            # Composition root: bind repo interfaces, register module routes
└── Support/                              # (optional) shared cross-cutting helpers — keep minimal

resources/js/
├── pages/[module]/                       # Inertia pages mirror backend modules
├── components/  components/ui/           # Shared React components + primitives
└── layouts/                              # App / auth / settings shells

routes/                                   # web.php, settings.php, console.php (global + module includes)
tests/Feature/[Module]/                   # Pest feature tests mirror module structure
```

## Dependency Rules

Dependencies flow strictly **downward**. Inner layers never import outer layers.

```text
Controller → Service → Repository (interface) → Model → Database
                 └──────────────→ Model (domain methods)
```

- ✅ Controllers depend on Services; Services depend on Repository **interfaces** and Models.
- ✅ Repository implementations depend on Eloquent Models; interfaces are bound to implementations in a Service Provider (composition root).
- ✅ Modules depend on `app/Concerns` / `app/Support` (shared) and on the framework.
- ❌ Controllers must not call Repositories directly or contain business logic (no layer skipping).
- ❌ Models must not depend on Services or Controllers (no upward dependencies).
- ❌ A module must not reach into another module's internals — cross-module calls go through a module's public Service or via events.

## Layer/Module Communication

- **Within a module:** Controller injects a Service (constructor injection); Service injects Repository interfaces and calls Model domain methods.
- **Presentation:** Controllers return `Inertia::render('module/page', $props)` or a redirect. Validation happens in Form Requests, not controllers.
- **Persistence:** Repositories encapsulate Eloquent queries behind an interface so query logic stays out of services and swapping storage stays possible.
- **Cross-module:** Prefer a public Service method or a Laravel Event/Listener. Never import another module's Repository or private classes. Avoid circular module dependencies — extract shared types/events instead.
- **Wiring:** Bind interfaces to implementations and register module routes in a dedicated `ServiceProvider` (the composition root).

## Key Principles

1. **Rich models, thin services.** Domain rules, invariants, and state changes live in Eloquent models (methods like `$campaign->schedule()`), not scattered across services. Services orchestrate: load → call model method → persist → dispatch events.
2. **Dependency inversion (lightweight).** Depend on Repository interfaces, bound to Eloquent implementations in a provider. This keeps services testable and prepares a future Infrastructure split.
3. **Module boundaries.** Each module owns a feature area and exposes a public API (its Services). Other modules use that API only.
4. **Thin controllers.** Controllers validate (via Form Requests), call one or two service methods, and render/redirect. No business branching.
5. **Minimal shared.** Keep `app/Concerns` / `app/Support` small; a bloated shared folder signals a missing module boundary.
6. **Frontend mirrors backend.** Inertia pages and components are organized to match backend modules; reuse `components/ui` primitives before adding new ones.

## Code Examples

### Rich Eloquent model (domain logic inside the model)

```php
// app/Modules/Campaigns/Models/Campaign.php
namespace App\Modules\Campaigns\Models;

use App\Modules\Campaigns\Exceptions\CampaignAlreadyScheduled;
use Illuminate\Database\Eloquent\Model;

class Campaign extends Model
{
    protected $fillable = ['name', 'status', 'scheduled_for'];

    public function schedule(\DateTimeInterface $when): void
    {
        if ($this->status !== 'draft') {
            throw new CampaignAlreadyScheduled($this->id);
        }

        $this->status = 'scheduled';
        $this->scheduled_for = $when;
    }
}

// ❌ AVOID: anemic model + business rule leaking into the service
// if ($campaign->status !== 'draft') { throw ... }   // belongs in Campaign::schedule()
```

### Repository interface + Eloquent adapter, bound in the composition root

```php
// app/Modules/Campaigns/Repositories/CampaignRepository.php
namespace App\Modules\Campaigns\Repositories;

use App\Modules\Campaigns\Models\Campaign;

interface CampaignRepository
{
    public function findOrFail(int $id): Campaign;

    public function save(Campaign $campaign): void;
}

// app/Modules/Campaigns/Repositories/EloquentCampaignRepository.php
class EloquentCampaignRepository implements CampaignRepository
{
    public function findOrFail(int $id): Campaign
    {
        return Campaign::query()->findOrFail($id);
    }

    public function save(Campaign $campaign): void
    {
        $campaign->save();
    }
}

// app/Providers/AppServiceProvider.php (composition root)
public function register(): void
{
    $this->app->bind(CampaignRepository::class, EloquentCampaignRepository::class);
}
```

### Application Service + thin Inertia controller (dependency rule in action)

```php
// app/Modules/Campaigns/Services/ScheduleCampaignService.php
class ScheduleCampaignService
{
    public function __construct(private CampaignRepository $campaigns) {}

    public function execute(int $campaignId, \DateTimeInterface $when): Campaign
    {
        $campaign = $this->campaigns->findOrFail($campaignId);
        $campaign->schedule($when);          // ← domain rule stays in the model
        $this->campaigns->save($campaign);

        return $campaign;
    }
}

// app/Modules/Campaigns/Controllers/CampaignScheduleController.php
class CampaignScheduleController
{
    public function store(ScheduleCampaignRequest $request, int $campaign, ScheduleCampaignService $service): RedirectResponse
    {
        $service->execute($campaign, $request->date('scheduled_for'));  // ← controller only orchestrates

        return to_route('campaigns.show', $campaign);
    }
}
```

## Anti-Patterns

- ❌ **Anemic models:** Eloquent models used as data bags while all logic sits in services. Push invariants into model methods.
- ❌ **Fat controllers:** business branching, calculations, or direct Eloquent queries in a controller. Move logic to a service/model; move queries to a repository.
- ❌ **Layer skipping:** controllers calling repositories or running `Model::query()` directly instead of going through a service.
- ❌ **Upward dependencies:** a model or repository importing a service or controller.
- ❌ **Cross-module reach-in:** importing another module's Repository/internal classes. Use the other module's public Service or an event.
- ❌ **Bloated `shared`/`Support`:** dumping ground for logic that actually belongs to a module.
