<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\IoT\DeviceTypes;

use App\Domain\IoT\Models\DeviceType;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class DeviceTypeResource extends Resource
{
    protected static ?string $model = DeviceType::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCube;

    protected static ?int $navigationSort = 1;

    public static function getNavigationGroup(): ?string
    {
        return __('IoT Management');
    }

    public static function getNavigationLabel(): string
    {
        return __('Device Types');
    }

    public static function form(Schema $schema): Schema
    {
        return Schemas\DeviceTypeForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return Schemas\DeviceTypeInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return Tables\DeviceTypesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListDeviceTypes::route('/'),
            'create' => Pages\CreateDeviceType::route('/create'),
            'view' => Pages\ViewDeviceType::route('/{record}'),
            'edit' => Pages\EditDeviceType::route('/{record}/edit'),
        ];
    }
}
