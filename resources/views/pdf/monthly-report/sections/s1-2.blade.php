@if(empty($data['rows']))
    <p class="placeholder">No data.</p>
@else
    <table class="grid">
        <tr><th>Party</th><th>Company</th><th>Address</th><th>Contacts</th></tr>
        @foreach($data['rows'] as $row)
            <tr class="avoid">
                <td>{{ $row['party'] ?? '' }}</td>
                <td>{{ $row['company'] ?? '' }}</td>
                <td>{{ $row['address'] ?? '' }}</td>
                <td>
                    @forelse(($row['contacts'] ?? []) as $contact)
                        <div>{{ $contact['name'] ?? '' }}@if(!empty($contact['designation'])) ({{ $contact['designation'] }})@endif</div>
                        @if(!empty($contact['tel']))<div>Tel: {{ $contact['tel'] }}</div>@endif
                        @if(!empty($contact['email']))<div>Email: {{ $contact['email'] }}</div>@endif
                    @empty
                        -
                    @endforelse
                </td>
            </tr>
        @endforeach
    </table>
@endif
