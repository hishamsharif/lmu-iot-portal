<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\IoTDashboards\Schemas;

use App\Domain\IoTDashboard\Models\IoTDashboard;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Unique;

class IoTDashboardForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Dashboard Details')
                    ->schema([
                        Select::make('organization_id')
                            ->relationship('organization', 'name')
                            ->searchable()
                            ->preload()
                            ->required(),

                        TextInput::make('name')
                            ->required()
                            ->maxLength(255)
                            ->live(onBlur: true)
                            ->afterStateUpdated(function (Set $set, Get $get, mixed $state): void {
                                $currentSlug = trim((string) $get('slug'));

                                if ($currentSlug !== '') {
                                    return;
                                }

                                $set('slug', Str::slug((string) $state));
                            }),

                        TextInput::make('slug')
                            ->required()
                            ->maxLength(180)
                            ->helperText('Unique within the selected organization.')
                            ->unique(
                                table: IoTDashboard::class,
                                column: 'slug',
                                ignoreRecord: true,
                                modifyRuleUsing: fn (Unique $rule, Get $get): Unique => $rule
                                    ->where('organization_id', (int) $get('organization_id')),
                            ),

                        TextInput::make('refresh_interval_seconds')
                            ->label('Refresh interval (seconds)')
                            ->integer()
                            ->minValue(2)
                            ->maxValue(300)
                            ->default(10)
                            ->required(),

                        Toggle::make('is_active')
                            ->default(true)
                            ->required(),

                        Textarea::make('description')
                            ->maxLength(500)
                            ->rows(3)
                            ->columnSpanFull(),
                    ])
                    ->columns(2),
            ]);
    }
}
