<?php

declare(strict_types=1);

namespace App\Filament\Admin\Pages;

use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;

class TelemetryViewer extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBugAnt;

    protected static ?int $navigationSort = 6;

    protected string $view = 'filament.admin.pages.telemetry-viewer';

    public static function getNavigationGroup(): ?string
    {
        return __('Debugging');
    }

    public static function getNavigationLabel(): string
    {
        return __('Telemetry Viewer');
    }
}
