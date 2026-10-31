<?php

namespace Database\Factories;

use App\Models\Activity;
use Illuminate\Database\Eloquent\Factories\Factory;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
/**
 * @extends Factory<Activity>
 */
class ActivityFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            TextInput::make('title')
            ->required()
    ->maxLength(150)
    ->placeholder('e.g. Daily SMS count vs SMS count from logs'),

Select::make('category')
    ->options(\App\Models\Activity::CATEGORIES)
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
        ];
    }
}
