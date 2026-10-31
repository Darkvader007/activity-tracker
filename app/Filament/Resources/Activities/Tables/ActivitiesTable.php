<?php

namespace App\Filament\Resources\Activities\Tables;

use App\Models\Activity;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class ActivitiesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')
                    ->searchable()
                    ->sortable()
                    ->wrap(),

                TextColumn::make('category')
                    ->badge()
                    ->sortable(),

                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),

                TextColumn::make('updates_count')
                    ->label('Updates')
                    ->counts('updates')
                    ->sortable(),

                TextColumn::make('creator.name')
                    ->label('Created by')
                    ->toggleable(),

                TextColumn::make('created_at')
                    ->dateTime('d M Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('category')
                    ->options(Activity::CATEGORIES),

                TernaryFilter::make('is_active')
                    ->label('Active'),

                TrashedFilter::make(),
            ]);
    }
}
