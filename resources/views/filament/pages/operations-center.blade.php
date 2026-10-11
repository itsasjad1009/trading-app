<x-filament-panels::page>
    <style>
[data-testid="staff-module"]{min-width:0}
[data-testid="staff-module"] .staff-records{max-width:100%;min-width:0;overscroll-behavior-x:contain}
@media(max-width:1023px){[data-testid="staff-module"] input,[data-testid="staff-module"] select,[data-testid="staff-module"] textarea{max-width:100%;width:100%;min-height:44px!important;font-size:16px!important}[data-testid="staff-module"] button,[data-testid="staff-module"] td a{min-height:44px}[data-testid="staff-module"] td:last-child{min-width:190px}[data-testid="staff-module"] nav{flex-wrap:wrap}}
</style>
<div data-testid="staff-module" data-module="{{ $module }}" style="display:grid;gap:16px;font-size:14px">
    <p>Demo operations only. Monetary reviews post balanced ledger entries. Trading outcomes cannot be edited.</p>
    <div style="display:flex;flex-wrap:wrap;gap:12px">@foreach($this->modules() as $name)<x-filament::button wire:click="$set('module', '{{ $name }}')" color="{{ $module === $name ? 'primary' : 'gray' }}">{{ ucfirst($name) }}</x-filament::button>@endforeach</div>
    @if(in_array($module,['deposits','withdrawals','kyc']))
        <label>Review reason <input wire:model="note" style="color:#edf2f8;background:#0b0e14;border:1px solid #303745;font-size:14px;min-height:40px;padding:10px;border-radius:8px;width:100%" /></label>
        <label>Step-up account password <input type="password" wire:model="password" autocomplete="current-password" style="color:#edf2f8;background:#0b0e14;border:1px solid #303745;font-size:14px;min-height:40px;padding:10px;border-radius:8px" /></label>
        @error('note')<p>{{ $message }}</p>@enderror
    @endif
    @if($module==='support')<label>Response <textarea wire:model="reply" style="color:#edf2f8;background:#0b0e14;border:1px solid #303745;font-size:14px;min-height:40px;width:100%;padding:10px"></textarea></label>@endif
    @if(in_array($module,['users','rules']))<label>Step-up password <input type="password" wire:model="password" autocomplete="current-password" style="color:#edf2f8;background:#0b0e14;border:1px solid #303745;font-size:14px;min-height:40px;padding:10px;border-radius:8px" /></label>@endif
    @if($module==='rules')<p>Demo profit basis points; 7000 means 70%. Changes affect new trades only.</p>@foreach([60,90,120,180,360] as $seconds)<label>{{ $seconds }}s <input type="number" min="0" max="10000" wire:model="payouts.{{ $seconds }}" style="color:#edf2f8;background:#0b0e14;border:1px solid #303745;font-size:14px;min-height:40px;padding:8px" /></label>@endforeach<x-filament::button wire:click="savePayouts">Save demo payout rules</x-filament::button>@endif
    @if($module==='settings')<p>Real-money processing and referral rewards are disabled. Settings editing is not yet available.</p>@endif
    <div class="staff-records" tabindex="0" role="region" aria-label="Staff records, scroll for columns" style="overflow:auto"><table style="width:100%;font-size:14px;text-align:left">
    @php($records=$this->records())
    @php($kycDocuments=$module==='kyc'?\Illuminate\Support\Facades\DB::table('kyc_documents')->whereIn('kyc_application_id',$records->pluck('id'))->get()->groupBy('kyc_application_id'):collect())
    @php($supportMessages=$module==='support'?\Illuminate\Support\Facades\DB::table('support_messages')->whereIn('support_conversation_id',$records->pluck('id'))->latest('id')->groupLimit(20,'support_conversation_id')->get()->groupBy('support_conversation_id'):collect())
    @foreach($records as $record)
        @if($loop->first)<thead><tr>@foreach((array)$record as $key=>$value)<th style="padding:12px">{{ $key }}</th>@endforeach<th>Actions</th></tr></thead><tbody>@endif
        <tr>@foreach((array)$record as $value)<td style="padding:12px;max-width:240px;overflow-wrap:anywhere">{{ $value }}</td>@endforeach
        <td style="padding:12px">
        @if(in_array($module,['deposits','withdrawals','kyc']) && in_array($record->status,['pending','under_review']))
            <x-filament::button wire:click="review({{ $record->id }}, 'approved')" size="sm">Approve</x-filament::button>
            <x-filament::button wire:click="review({{ $record->id }}, 'rejected')" color="danger" size="sm">Reject</x-filament::button>
            @if($module==='kyc')
                @if($record->status==='pending')<x-filament::button wire:click="review({{ $record->id }}, 'under_review')" color="gray" size="sm">Start review</x-filament::button>@endif
                <x-filament::button wire:click="review({{ $record->id }}, 'resubmission_required')" color="warning" size="sm">Request resubmission</x-filament::button>
            @endif
        @endif
        @if($module==='kyc')@foreach($kycDocuments[$record->id]??collect() as $doc)<a href="{{ route('kyc.document',$doc->id) }}">{{ $doc->side }} document</a> @endforeach @endif
        @if($module==='users')<x-filament::button wire:click="toggleUser({{ $record->id }})" size="sm">Toggle status</x-filament::button><select wire:model="roles.{{ $record->id }}" style="color:#edf2f8;background:#0b0e14;border:1px solid #303745;font-size:14px;min-height:40px;padding:8px;border-radius:8px">@foreach(['customer','super_admin','operations_admin','kyc_officer','support_agent','auditor'] as $role)<option value="{{ $role }}">{{ $role }}</option>@endforeach</select><x-filament::button wire:click="saveRole({{ $record->id }})" size="sm">Set role</x-filament::button>@endif
        @if($module==='pairs')<label>Exchange Spot symbol <select wire:model="exchangeSymbols.{{ $record->id }}" style="background:#0b0e14;padding:8px">@foreach(config('market-data.symbols') as $symbol)<option>{{ $symbol }}</option>@endforeach</select></label><x-filament::button wire:click="saveMarketSymbol({{ $record->id }})" size="sm">Validate and save source</x-filament::button>@error('exchangeSymbols.'.$record->id)<p>{{ $message }}</p>@enderror<x-filament::button wire:click="togglePair({{ $record->id }})" size="sm">Toggle enabled</x-filament::button>@endif
        @if($module==='support')
            @foreach($supportMessages[$record->id]??collect() as $msg)<p>#{{ $msg->user_id }}: {{ $msg->body }}</p>@endforeach
            <x-filament::button wire:click="respond({{ $record->id }})" size="sm">Send response</x-filament::button>
            <x-filament::button wire:click="setConversationStatus({{ $record->id }}, '{{ $record->status === 'closed' ? 'open' : 'closed' }}')" color="gray" size="sm">{{ $record->status === 'closed' ? 'Reopen conversation' : 'Close conversation' }}</x-filament::button>
        @endif
        </td></tr>
        @if($loop->last)</tbody>@endif
    @endforeach
    </table></div><nav aria-label="Staff results pagination" style="display:flex;align-items:center;gap:12px">
        <x-filament::button wire:click="previousPage" :disabled="$records->onFirstPage()" color="gray" size="sm">Previous</x-filament::button>
        <span>Page {{ $records->currentPage() }} of {{ $records->lastPage() }}</span>
        <x-filament::button wire:click="nextPage" :disabled="!$records->hasMorePages()" color="gray" size="sm">Next</x-filament::button>
    </nav>
    @if($records->isEmpty())<p>No records in this module yet.</p>@endif
    </div>
</x-filament-panels::page>


