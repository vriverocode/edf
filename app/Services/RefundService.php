namespace App\Services;

use App\Models\Pay;
use App\Models\Refund;
use Illuminate\Support\Facades\DB;

class RefundService
{
    public function processRefund($bookingId, $amount, $reason)
    {
        return DB::transaction(function () use ($bookingId, $amount, $reason) {
            $pay = Pay::where('booking_id', $bookingId)
                      ->whereIn('status', ['completed', 'partially_refunded'])
                      ->firstOrFail();

            $alreadyRefunded = $pay->refunds()->sum('amount');
            $remainingBalance = $pay->amount - $alreadyRefunded;

            if ($amount > $remainingBalance) {
                throw new \Exception('El monto excede el saldo disponible.');
            }

            $refund = Refund::create([
                'booking_id' => $bookingId,
                'pay_id'     => $pay->id,
                'amount'     => $amount,
                'reason'     => $reason,
                'status'     => 'completed'
            ]);

            $newTotal = $alreadyRefunded + $amount;
            $pay->update(['status' => $newTotal >= $pay->amount ? 'refunded' : 'partially_refunded']);

            return $refund;
        });
    }
}
