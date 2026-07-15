<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use App\Services\StaffAccessService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Hash;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    /** @var array<int, int|string> */
    private array $activeWorkLocationIds = [];

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $this->activeWorkLocationIds = $data['active_work_location_ids'] ?? [];
        unset($data['active_work_location_ids']);

        $data['password'] = Hash::make($data['password']);
        
        return $data;
    }

    protected function afterCreate(): void
    {
        app(StaffAccessService::class)->syncActiveLocations(
            $this->record,
            $this->activeWorkLocationIds
        );
    }
}
