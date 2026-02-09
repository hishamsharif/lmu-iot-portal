<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\DeviceManagement\DeviceTypes\RelationManagers;

use App\Domain\DeviceManagement\Models\DeviceType;
use App\Domain\DeviceSchema\Models\DeviceSchema;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class DeviceSchemasRelationManager extends RelationManager
{
    protected static string $relationship = 'schemas';

    public function getOwnerRecord(): DeviceType
    {
        /** @var DeviceType $ownerRecord */
        $ownerRecord = $this->ownerRecord;

        return $ownerRecord;
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required()
                    ->maxLength(255)
                    ->helperText('Name for this device schema contract'),

                Toggle::make('create_initial_version')
                    ->label('Create Initial Version')
                    ->default(true)
                    ->helperText('Creates v1 immediately so the device type is ready for onboarding.'),

                Select::make('initial_version_status')
                    ->label('Initial Version Status')
                    ->options([
                        'active' => 'Active',
                        'draft' => 'Draft',
                    ])
                    ->default('active')
                    ->visible(fn (Get $get): bool => (bool) $get('create_initial_version')),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('versions_count')
                    ->label('Versions')
                    ->counts('versions'),

                TextColumn::make('active_versions_count')
                    ->label('Active Versions')
                    ->state(fn (DeviceSchema $record): int => $record->versions()->where('status', 'active')->count()),

                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->headerActions([
                CreateAction::make()
                    ->using(function (array $data): DeviceSchema {
                        $schema = $this->getOwnerRecord()->schemas()->create([
                            'name' => (string) $data['name'],
                        ]);

                        $shouldCreateVersion = (bool) ($data['create_initial_version'] ?? true);

                        if ($shouldCreateVersion) {
                            $status = (string) ($data['initial_version_status'] ?? 'active');

                            $schema->versions()->create([
                                'version' => 1,
                                'status' => in_array($status, ['active', 'draft'], true) ? $status : 'active',
                                'notes' => 'Initial version created during device type onboarding.',
                            ]);
                        }

                        return $schema;
                    }),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
