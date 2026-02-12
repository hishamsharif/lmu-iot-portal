<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Automation\AutomationWorkflows\Schemas;

use App\Domain\Automation\Enums\AutomationWorkflowStatus;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class AutomationWorkflowForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Workflow Details')
                    ->schema([
                        Select::make('organization_id')
                            ->relationship('organization', 'name')
                            ->searchable()
                            ->preload()
                            ->required(),

                        TextInput::make('name')
                            ->required()
                            ->maxLength(255),

                        TextInput::make('slug')
                            ->required()
                            ->maxLength(255)
                            ->helperText('Unique per organization. Spaces will be normalized when saved.'),

                        Select::make('status')
                            ->options(self::statusOptions())
                            ->default(AutomationWorkflowStatus::Draft->value)
                            ->required(),
                    ])
                    ->columns(2),
            ]);
    }

    /**
     * @return array<string, string>
     */
    private static function statusOptions(): array
    {
        $options = [];

        foreach (AutomationWorkflowStatus::cases() as $status) {
            $options[$status->value] = Str::headline($status->name);
        }

        return $options;
    }
}
