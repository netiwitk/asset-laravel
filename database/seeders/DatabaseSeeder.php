<?php

namespace Database\Seeders;

use App\Enums\Condition;
use App\Enums\Role;
use App\Models\Asset;
use App\Models\Category;
use App\Models\Department;
use App\Models\Loan;
use App\Models\User;
use App\Services\AssetLedger;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Demo data. Every status change goes through AssetLedger, exactly like the UI,
 * so the movement history is consistent with each asset's current state.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $root = Department::create(['code' => 'HQ', 'name' => 'สำนักงานใหญ่']);
        $departments = collect([
            ['FIN', 'กองคลัง'],
            ['IT', 'กองเทคโนโลยีสารสนเทศ'],
            ['HR', 'ฝ่ายทรัพยากรบุคคล'],
            ['OPS', 'ฝ่ายปฏิบัติการ'],
        ])->map(fn (array $row) => Department::create(['parent_id' => $root->id, 'code' => $row[0], 'name' => $row[1]]));

        $fin = $departments[0];
        $it = $departments[1];

        $admin = User::factory()->create(['name' => 'ผู้ดูแลระบบ', 'email' => 'admin@demo.test', 'role' => Role::Admin, 'department_id' => $it->id]);
        $officer = User::factory()->create(['name' => 'สมศรี พัสดุดี', 'email' => 'officer@demo.test', 'role' => Role::Officer, 'department_id' => $fin->id]);
        $staff = User::factory()->create(['name' => 'สมชาย ใจดี', 'email' => 'staff@demo.test', 'role' => Role::Staff, 'department_id' => $it->id]);
        $others = collect(['วิภา รักงาน', 'ธนพล มั่นคง', 'กมล ศรีสุข', 'ปวีณา ทองดี'])
            ->map(fn (string $name, int $i) => User::factory()->create([
                'name' => $name,
                'email' => 'user'.($i + 1).'@demo.test',
                'role' => Role::Staff,
                'department_id' => $departments[$i % $departments->count()]->id,
            ]));

        $categories = collect([
            'COM' => ['คอมพิวเตอร์และอุปกรณ์', 5, ['Notebook Dell Latitude 5450', 'Notebook Lenovo ThinkPad E14', 'คอมพิวเตอร์ตั้งโต๊ะ HP ProDesk', 'จอภาพ Dell 24 นิ้ว', 'iPad Air 11 นิ้ว', 'เครื่องพิมพ์ Brother Laser']],
            'OFF' => ['ครุภัณฑ์สำนักงาน', 10, ['โต๊ะทำงานเหล็ก', 'เก้าอี้สำนักงาน', 'ตู้เอกสาร 4 ลิ้นชัก', 'เครื่องถ่ายเอกสาร Canon', 'เครื่องปรับอากาศ 18000 BTU']],
            'AV' => ['โสตทัศนูปกรณ์', 5, ['โปรเจกเตอร์ Epson', 'กล้องถ่ายภาพ Sony A7', 'ไมโครโฟนไร้สาย', 'ลำโพงห้องประชุม', 'จอ LED 65 นิ้ว']],
            'VEH' => ['ยานพาหนะ', 8, ['รถยนต์ Toyota Commuter', 'รถจักรยานยนต์ Honda Wave']],
        ])->map(function (array $row, string $code) {
            return ['model' => Category::create(['code' => $code, 'name' => $row[0], 'useful_life_years' => $row[1]]), 'items' => $row[2]];
        });

        // The register went live 60 days ago; later activity is dated relative to that.
        Carbon::setTestNow(today()->subDays(60)->setTime(9, 0));
        // The clock is frozen while seeding; always unfreeze it, even if seeding fails.
        try {

            $assets = collect();
            $sequence = 0;
            foreach ($categories as $code => $category) {
                foreach ($category['items'] as $name) {
                    foreach (range(1, $code === 'VEH' ? 1 : 3) as $copy) {
                        $sequence++;
                        $acquired = Carbon::create(2021, 1, 1)->addDays(($sequence * 37) % 1700);
                        $assets->push(AssetLedger::register([
                            'asset_tag' => sprintf('%s-%02d-%04d', $code, ($acquired->year + 543) % 100, $sequence),
                            'name' => $name,
                            'category_id' => $category['model']->id,
                            'custodian_id' => $sequence % 4 === 0 ? $others[$sequence % $others->count()]->id : null,
                            'serial_no' => 'SN'.str_pad((string) ($sequence * 7919), 8, '0', STR_PAD_LEFT),
                            'acquired_on' => $acquired,
                            'cost' => match ($code) {
                                'VEH' => 1_250_000 + $sequence * 1000,
                                'COM' => 18_000 + ($sequence % 5) * 6_500,
                                'AV' => 9_500 + ($sequence % 4) * 12_000,
                                default => 3_500 + ($sequence % 6) * 2_800,
                            },
                            'location_note' => 'อาคาร '.['A', 'B', 'C'][$sequence % 3].' ชั้น '.(1 + $sequence % 5),
                        ], $departments[$sequence % $departments->count()]->id, $officer, 'ยกยอดจากทะเบียนเดิม'));
                    }
                }
            }

            $this->seedActivity($assets, $officer, $staff, $others);
        } finally {
            Carbon::setTestNow();
        }
    }

    /**
     * A realistic mix: loans in every status, an overdue loan, repairs, a transfer and a disposal.
     *
     * @param  Collection<int, Asset>  $assets
     * @param  Collection<int, User>  $others
     */
    private function seedActivity(Collection $assets, User $officer, User $staff, Collection $others): void
    {
        $today = Carbon::now()->addDays(60)->startOfDay();
        $at = fn (int $daysAgo, int $hour = 10) => Carbon::setTestNow($today->copy()->subDays($daysAgo)->setTime($hour, 0));

        $request = function (Asset $asset, User $borrower, int $daysAgo, int $loanDays, string $purpose) use ($at): Loan {
            $at($daysAgo, 9);

            return Loan::create([
                'asset_id' => $asset->id,
                'requester_id' => $borrower->id,
                'borrower_id' => $borrower->id,
                'requested_at' => now(),
                'due_on' => today()->addDays($loanDays),
                'purpose' => $purpose,
            ]);
        };

        $pick = fn (string $tagPrefix, int $nth) => $assets->filter(fn (Asset $asset) => str_starts_with($asset->asset_tag, $tagPrefix))->values()[$nth];

        // Returned loans (history)
        foreach ([[0, 30, 'ประชุมนอกสถานที่'], [3, 20, 'อบรมพนักงานใหม่']] as [$nth, $daysAgo, $purpose]) {
            $loan = $request($pick('COM', $nth), $others[$nth % $others->count()], $daysAgo, 5, $purpose);
            AssetLedger::approve($loan, $officer);
            AssetLedger::handOver($loan, $officer);
            $at($daysAgo - 4, 16);
            AssetLedger::receiveReturn($loan, $officer, Condition::Usable);
        }

        // Currently on loan, one of them overdue
        $loan = $request($pick('AV', 0), $staff, 12, 7, 'ถ่ายวิดีโอสัมมนาประจำปี');
        AssetLedger::approve($loan, $officer);
        AssetLedger::handOver($loan, $officer);

        $loan = $request($pick('COM', 1), $others[0], 2, 14, 'ทำงานนอกสถานที่');
        AssetLedger::approve($loan, $officer);
        AssetLedger::handOver($loan, $officer);

        // Approved, waiting for hand-over
        AssetLedger::approve($request($pick('AV', 3), $others[1], 1, 3, 'นำเสนองานลูกค้า'), $officer);

        // Pending requests for the officer to try
        $request($pick('COM', 6), $staff, 0, 7, 'ใช้ทดสอบระบบใหม่');
        $request($pick('VEH', 0), $others[2], 0, 2, 'เดินทางไปตรวจงานต่างจังหวัด');
        $request($pick('AV', 6), $others[3], 0, 1, 'ประชุมผู้บริหาร');

        // Returned damaged, then sent to repair
        $loan = $request($pick('COM', 9), $others[1], 25, 5, 'ออกบูธงานแสดงสินค้า');
        AssetLedger::approve($loan, $officer);
        AssetLedger::handOver($loan, $officer);
        $at(19, 15);
        AssetLedger::receiveReturn($loan, $officer, Condition::Damaged, 'จอแตกมุมขวา');
        $at(18);
        AssetLedger::sendToRepair($loan->asset, $officer, 'บริษัท ไอทีเซอร์วิส จำกัด', $today->copy()->addDays(5)->toDateString(), 'เปลี่ยนจอ');

        // Damaged, waiting to be sent for repair
        $loan = $request($pick('OFF', 4), $others[2], 15, 3, 'ห้องประชุมชั่วคราว');
        AssetLedger::approve($loan, $officer);
        AssetLedger::handOver($loan, $officer);
        $at(11, 15);
        AssetLedger::receiveReturn($loan, $officer, Condition::Damaged, 'ขาเก้าอี้หัก');

        // Repaired and back in service
        $at(40);
        AssetLedger::sendToRepair($pick('OFF', 9), $officer, 'ร้านแอร์บริการ', null, 'ล้างแอร์ประจำปี');
        $at(37, 14);
        AssetLedger::receiveFromRepair($pick('OFF', 9), $officer, Condition::Usable, '1800', 'ล้างและเติมน้ำยา');

        // Transfer and disposal
        $at(33);
        $moved = $pick('OFF', 0);
        $target = Department::query()->whereNotNull('parent_id')->whereKeyNot($moved->department_id)->value('id');
        AssetLedger::transfer($moved, $officer, $target, 'ย้ายตามโครงสร้างใหม่');
        $at(28, 11);
        AssetLedger::dispose($pick('OFF', 12), $officer, 'ชำรุดเกินซ่อม มติคณะกรรมการ');
    }
}
