<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Menu;

class MenuSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $parent = Menu::create(['name_ar' => 'الرسائل القصيرة', 'name_en' => 'Sms']);

        Menu::create([
            'name_ar' => 'أرسل رسالة نصية قصيرة',
            'name_en' => 'Send an SMS',
            'route_name' => 'sms.send',
            'parent_id' => $parent->id
        ]);

        $sub_parent = Menu::create([
            'name_ar' => 'الرسائل',
            'name_en' => 'Messages',
            'parent_id' => $parent->id
        ]);

        $sub_parent = Menu::create([
            'name_ar' => 'الرسائل المرسلة',
            'name_en' => 'Sent Messages',
            'route_name' => 'MessagesSent.index',
            'parent_id' => $parent->id,
            'sub_parent_id' => $sub_parent->id
        ]);

        $sub_parent = Menu::create([
            'name_ar' => 'حذق الرسائل المرسلة',
            'name_en' => 'delete Sent Messages',
            'route_name' => 'MessagesSent.destroy',
            'parent_id' => $parent->id,
            'sub_parent_id' => $sub_parent->id,
            'operations' => 1,
        ]);
        $sub_parent = Menu::create([
            'name_ar' => 'حذف محدد الرسائل المرسلة',
            'name_en' => 'delete selected  Sent Messages',
            'route_name' => 'deleteSelectedSentSms',
            'parent_id' => $parent->id,
            'sub_parent_id' => $sub_parent->id,
            'operations' => 1,
        ]);



        $sub_parent = Menu::create([
            'name_ar' => 'تفاصيل الرسائل المرسلة',
            'name_en' => 'Details sent messages',
            'route_name' => 'SmsDetails.index',
            'parent_id' => $parent->id,
            'sub_parent_id' => $sub_parent->id
        ]);

        $sub_parent = Menu::create([
            'name_ar' => 'الرسائل اللاحقة',
            'name_en' => ' Later messages',
            'route_name' => 'SmsDetails.index',
            'parent_id' => $parent->id,
            'sub_parent_id' => $sub_parent->id
        ]);
    }
}
