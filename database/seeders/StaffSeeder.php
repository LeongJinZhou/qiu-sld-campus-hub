<?php

namespace Database\Seeders;

use App\Enums\ApproverRole;
use App\Enums\GlobalRole;
use App\Models\Staff;
use App\Models\User;
use Illuminate\Database\Seeder;

/** Demo staff. Approver names come from the official templates; emails are placeholders to replace. */
class StaffSeeder extends Seeder
{
    public function run(): void
    {
        $staff = [
            ['Mr Khor Desmond', 'Assistant Registrar', 'Registry', ApproverRole::AssistantRegistrar, 'assistant.registrar.demo'],
            ['Mr Muhammad MG Omar', 'Registrar', 'Registry', ApproverRole::Registrar, 'registrar.demo'],
            ['Ms Sumathi Ayadurai', 'Bursar', 'Finance and Accounts', ApproverRole::Bursar, 'bursar.demo'],
            ['Mr Nicholas Goh', 'Chief Operating Officer', null, ApproverRole::Coo, 'coo.demo'],
            ['Prof Dr Tan Wei Ming', 'Dean', 'Faculty of Computing', ApproverRole::Dean, 'dean.demo'],
            ['Dr Lim Siew Ling', 'Lecturer', 'Faculty of Computing', null, 'advisor1.demo'],
            ['Dr Ahmad Faiz Rahman', 'Senior Lecturer', 'Faculty of Business', null, 'advisor2.demo'],
            ['Ms Priya Nair', 'Lecturer', 'Faculty of Health Sciences', null, 'advisor3.demo'],
            ['Ms Nur Iman Zakaria', 'SLD Officer', 'Student Life Division', null, 'sld.officer.demo'],
        ];

        foreach ($staff as [$name, $title, $department, $approver, $local]) {
            $email = "{$local}@qiu.edu.my";
            $user = User::firstOrCreate(['email' => $email], ['name' => $name]);
            $user->assignRole(GlobalRole::Staff->value);
            Staff::updateOrCreate(['email' => $email], [
                'user_id' => $user->id,
                'name' => $name,
                'title' => $title,
                'department' => $department,
                'approver_role' => $approver,
            ]);
        }

        User::where('email', 'sld.officer.demo@qiu.edu.my')->firstOrFail()->assignRole(GlobalRole::Admin->value);
    }
}
