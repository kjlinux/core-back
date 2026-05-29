@extends('reports.layout')

@section('content')
    @if(!empty($summary))
        <table class="summary">
            <tr>
                @foreach($summary as $item)
                    <td>
                        <div class="label">{{ $item['label'] }}</div>
                        <div class="value">{{ $item['value'] }}</div>
                    </td>
                @endforeach
            </tr>
        </table>
    @endif

    <table class="data">
        <thead>
            <tr>
                @foreach($headers as $h)
                    <th>{{ $h }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @forelse($rows as $row)
                <tr>
                    @foreach($row as $cell)
                        <td>{{ $cell }}</td>
                    @endforeach
                </tr>
            @empty
                <tr><td colspan="{{ count($headers) }}">Aucune donnee.</td></tr>
            @endforelse
        </tbody>
    </table>
@endsection
