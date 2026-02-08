<?php

namespace App\Providers;

use App\Domain\DeviceManagement\Publishing\Nats\BasisNatsPublisherFactory;
use App\Domain\DeviceManagement\Publishing\Nats\NatsPublisherFactory;
use App\Domain\Shared\Models\User;
use Filament\Events\TenantSet;
use Filament\Facades\Filament;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\FileUpload;
use Filament\Infolists\Components\ImageEntry;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(NatsPublisherFactory::class, BasisNatsPublisherFactory::class);

        if ($this->app->environment('local')) {
            $this->app->register(\Laravel\Telescope\TelescopeServiceProvider::class);
            $this->app->register(TelescopeServiceProvider::class);
        }
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Preserve v3 behavior for file visibility (files default to public instead of private)
        FileUpload::configureUsing(fn (FileUpload $fileUpload) => $fileUpload
            ->visibility('public'));

        ImageColumn::configureUsing(fn (ImageColumn $imageColumn) => $imageColumn
            ->visibility('public'));

        ImageEntry::configureUsing(fn (ImageEntry $imageEntry) => $imageEntry
            ->visibility('public'));

        // Preserve v3 behavior for table filters and pagination
        Table::configureUsing(fn (Table $table) => $table
            ->deferFilters(false)
            ->paginationPageOptions([5, 10, 25, 50, 'all']));

        // Preserve v3 behavior for layout components (span full width)
        Fieldset::configureUsing(fn (Fieldset $fieldset) => $fieldset
            ->columnSpanFull());

        Grid::configureUsing(fn (Grid $grid) => $grid
            ->columnSpanFull());

        Section::configureUsing(fn (Section $section) => $section
            ->columnSpanFull());

        // Preserve v3 behavior for unique validation (don't ignore current record by default)
        Field::configureUsing(fn (Field $field) => $field
            ->uniqueValidationIgnoresRecordByDefault(false));

        Gate::before(function (User $user, $ability) {
            return $user->isSuperAdmin() ? true : null;
        });

        Event::listen(TenantSet::class, function (): void {
            setPermissionsTeamId(Filament::getTenant()->id);

            Cache::store(config('permission.cache.store') === 'default' ? null : config('permission.cache.store'))
                ->forget(config('permission.cache.key'));
        });
    }
}
