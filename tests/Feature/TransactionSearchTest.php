<?php

use App\Models\MembershipPayment;
use App\Services\Finance\TransactionSearch;
use Illuminate\Support\Facades\DB;

it('finds transactions by club, student name and number even when a record has no transaction ID', function () {
    $f = proposalFixture();
    $student = otherStudent('STD20249911', 'Nadia Search');
    $payment = MembershipPayment::create(['club_id' => $f['club']->id, 'user_id' => $student->id, 'method' => 'cash', 'amount_sen' => 1000, 'status' => 'cash_pending']);
    DB::table('membership_payments')->where('id', $payment->id)->update(['order_id' => null]); // as on records from before IDs existed

    $search = app(TransactionSearch::class);
    expect($search->find('fixture club'))->toHaveCount(1)
        ->and($search->find('nadia'))->toHaveCount(1)
        ->and($search->find('STD20249911 cash'))->toHaveCount(1)
        ->and($search->find('nadia music'))->toHaveCount(0);
});
