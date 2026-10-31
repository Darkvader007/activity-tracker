<?php

namespace App\Filament\Resources\Activities\Schemas;

use App\Models\Activity;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class ActivityForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('title')
                    ->required()
                    ->maxLength(150)
                    ->placeholder('e.g. Daily SMS count vs SMS count from logs'),

                Select::make('category')
                    ->options(Activity::CATEGORIES)
                    ->required()
                    ->native(false),

                Textarea::make('description')
                    ->rows(3)
                    ->placeholder('How to perform this check, what to compare, who to escalate to')
                    ->columnSpanFull(),

                Toggle::make('is_active')
                    ->label('Active')
                    ->default(true)
                    ->helperText('Inactive activities are hidden from the daily board but keep their history.'),
            ]);
    }
}
