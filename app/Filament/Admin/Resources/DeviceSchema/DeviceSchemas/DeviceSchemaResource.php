<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\DeviceSchema\DeviceSchemas;

use App\Domain\DeviceSchema\Models\DeviceSchema;
use App\Filament\Admin\Resources\DeviceSchema\DeviceSchemas\Pages\CreateDeviceSchema;
use App\Filament\Admin\Resources\DeviceSchema\DeviceSchemas\Pages\EditDeviceSchema;
use App\Filament\Admin\Resources\DeviceSchema\DeviceSchemas\Pages\ListDeviceSchemas;
use App\Filament\Admin\Resources\DeviceSchema\DeviceSchemas\Pages\ViewDeviceSchema;
use App\Filament\Admin\Resources\DeviceSchema\DeviceSchemas\RelationManagers\DeviceSchemaVersionsRelationManager;
use App\Filament\Admin\Resources\DeviceSchema\DeviceSchemas\Schemas\DeviceSchemaForm;
use App\Filament\Admin\Resources\DeviceSchema\DeviceSchemas\Schemas\DeviceSchemaInfolist;
use App\Filament\Admin\Resources\DeviceSchema\DeviceSchemas\Tables\DeviceSchemasTable;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class DeviceSchemaResource extends Resource
{
    protected static ?string $model = DeviceSchema::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?int $navigationSort = 2;

    public static function getNavigationGroup(): ?string
    {
        return __('IoT Management');
    }

    public static function getNavigationLabel(): string
    {
        return __('Device Schemas');
    }

    public static function form(Schema $schema): Schema
    {
        return DeviceSchemaForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return DeviceSchemaInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return DeviceSchemasTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            DeviceSchemaVersionsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDeviceSchemas::route('/'),
            'create' => CreateDeviceSchema::route('/create'),
            'view' => ViewDeviceSchema::route('/{record}'),
            'edit' => EditDeviceSchema::route('/{record}/edit'),
        ];
    }
}
