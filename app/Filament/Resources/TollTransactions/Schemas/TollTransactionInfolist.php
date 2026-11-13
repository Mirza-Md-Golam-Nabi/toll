<?php

namespace App\Filament\Resources\TollTransactions\Schemas;

use App\Models\TollTransaction;
use App\Support\Money;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class TollTransactionInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(3)
            ->components([
                Section::make(__('Ticket'))
                    ->columnSpan(2)
                    ->columns(3)
                    ->components([
                        TextEntry::make('ticket_no')->label('Ticket')->fontFamily('mono')->weight('bold')->copyable(),
                        TextEntry::make('issued_at')->label('Issued at')->dateTime('d M Y, h:i:s A'),
                        TextEntry::make('status')->label('Status')->badge(),
                        TextEntry::make('category.name_bn')->label('Vehicle category')
                            ->formatStateUsing(fn (TollTransaction $record): string => $record->category->display_name),
                        TextEntry::make('amount')->label('Amount')->formatStateUsing(fn ($state): string => Money::format($state))->size('lg')->weight('bold')->color('primary'),
                        TextEntry::make('exempt_reason')->label('Exempt reason')->placeholder('—'),
                        TextEntry::make('operator.name')->label('Operator'),
                        TextEntry::make('booth.name')->label('Booth'),
                        TextEntry::make('print_count')->label('Prints'),
                    ]),
                Section::make(__('Number plate'))
                    ->columnSpan(1)
                    ->components([
                        TextEntry::make('plate_number')->label('Plate number')->fontFamily('mono')->size('lg')->weight('bold')->placeholder('—'),
                        TextEntry::make('plate_source')->label('Source')->badge(),
                        TextEntry::make('anpr_confidence')->label('ANPR confidence')->suffix('%')->placeholder('—'),
                        ImageEntry::make('plate_image')->label('Plate image')->visible(fn (TollTransaction $record): bool => filled($record->plate_image)),
                    ]),
                Section::make(__('Void details'))
                    ->columnSpanFull()
                    ->columns(4)
                    ->visible(fn (TollTransaction $record): bool => $record->isVoided())
                    ->components([
                        TextEntry::make('voided_at')->label('Voided at')->dateTime('d M Y, h:i A'),
                        TextEntry::make('voider.name')->label('Voided by'),
                        TextEntry::make('approver.name')->label('Approved by'),
                        TextEntry::make('void_reason')->label('Reason'),
                    ]),
                Section::make(__('Audit history'))
                    ->columnSpanFull()
                    ->collapsible()
                    ->components([
                        RepeatableEntry::make('auditLogs')
                            ->hiddenLabel()
                            ->columns(5)
                            ->components([
                                TextEntry::make('created_at')->label('Time')->dateTime('d M Y, h:i:s A'),
                                TextEntry::make('event')->label('Event')->badge(),
                                TextEntry::make('user.name')->label('User')->placeholder('System'),
                                TextEntry::make('approver.name')->label('Approved by')->placeholder('—'),
                                TextEntry::make('reason')->label('Reason')->placeholder('—'),
                            ]),
                    ]),
            ]);
    }
}
