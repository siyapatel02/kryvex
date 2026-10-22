<div class="admin-table-wrap">
    <table class="admin-table">
        <thead>
            <tr>
                <th>Symbol</th>
                <th>Side</th>
                <th>Type</th>
                <th>Qty</th>
                <th>Price</th>
                <th>Status</th>
                <th>Date</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($orders as $order)
                <tr>
                    <td>{{ $order['symbol'] }}</td>
                    <td>{{ $order['side'] }}</td>
                    <td>{{ $order['type'] }}</td>
                    <td>{{ $order['quantity'] }}</td>
                    <td>{{ $order['price'] }}</td>
                    <td><span class="admin-pill is-muted">{{ $order['status'] }}</span></td>
                    <td>{{ $order['created_at'] }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" class="admin-empty">No records found.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>