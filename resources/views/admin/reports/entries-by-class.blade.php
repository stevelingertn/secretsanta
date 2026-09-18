@extends($print ? 'layouts.print' : 'layouts.admin')

@section('content')
    @include('admin.reports._header')

    <div class="overflow-x-auto">
        <table class="table-plain">
            <thead>
                <tr>
                    <th>Class</th>
                    <th>Entries</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($rows as $row)
                    <tr>
                        <td>{{ $row['category']->name }}</td>
                        <td>{{ $row['count'] }}</td>
                    </tr>
                @empty
                    <tr><td colspan="2">No cars registered.</td></tr>
                @endforelse
            </tbody>
            <tfoot>
                <tr>
                    <td class="font-bold">Total</td>
                    <td class="font-bold">{{ $total }}</td>
                </tr>
            </tfoot>
        </table>
    </div>
@endsection
