<?php

namespace Database\Seeders;

use App\Models\Plan;
use App\Models\PlanBillingCycle;
use App\Models\PlanFeature;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class PlanSeeder extends Seeder
{
    public function run(): void
    {
        // Clear existing data (optional but recommended during development)
        PlanFeature::truncate();
        PlanBillingCycle::truncate();
        Plan::truncate();

        /*
        |--------------------------------------------------------------------------
        | FREE PLAN
        |--------------------------------------------------------------------------
        */
        $free = Plan::create([
            'name'        => 'Free',
            'code'        => 'free',
            'price_usd'   => 0.00,
            'price_khr'   => 0,
            'seats'       => 1,
            'storage_gb'  => 1,
            'api_limit'   => 500,
            'is_active'   => true,
        ]);

        $this->createBillingCycles($free, [
            ['label' => 'Monthly', 'months' => 1, 'discount_percent' => 0, 'is_active' => true],
        ]);

        $this->createFeatures($free, [
            ['key' => 'products_limit', 'en' => 'Up to 20 products',     'km' => 'អាចបន្ថែមផលិតផលបានរហូតដល់ 20'],
            ['key' => 'single_branch',  'en' => '1 branch only',         'km' => 'ប្រើបានតែ 1 សាខា'],
            ['key' => 'single_staff',   'en' => '1 staff account',       'km' => 'គណនីបុគ្គលិក 1'],
            ['key' => 'basic_pos',      'en' => 'Basic POS',             'km' => 'POS មូលដ្ឋាន'],
        ]);

        /*
        |--------------------------------------------------------------------------
        | STARTER PLAN
        |--------------------------------------------------------------------------
        */
        $starter = Plan::create([
            'name'        => 'Starter',
            'code'        => 'starter',
            'price_usd'   => 3.00,
            'price_khr'   => 12000,
            'seats'       => 2,
            'storage_gb'  => 5,
            'api_limit'   => 3000,
            'is_active'   => true,
        ]);

        $this->createBillingCycles($starter, [
            ['label' => 'Monthly', 'months' => 1, 'discount_percent' => 0,   'is_active' => true],
            ['label' => 'Quarterly', 'months' => 3, 'discount_percent' => 5, 'is_active' => true],
        ]);

        $this->createFeatures($starter, [
            ['key' => 'products_limit', 'en' => 'Up to 50 products',     'km' => 'អាចបន្ថែមផលិតផលបានរហូតដល់ 50'],
            ['key' => 'branches_limit', 'en' => 'Up to 2 branches',      'km' => 'អាចប្រើបាន 2 សាខា'],
            ['key' => 'staff_limit',    'en' => 'Up to 2 staff accounts','km' => 'គណនីបុគ្គលិករហូតដល់ 2'],
            ['key' => 'sales_report',   'en' => 'Sales reports',         'km' => 'របាយការណ៍លក់'],
            ['key' => 'inventory',      'en' => 'Inventory management',  'km' => 'គ្រប់គ្រងស្តុក'],
        ]);

        /*
        |--------------------------------------------------------------------------
        | PRO PLAN
        |--------------------------------------------------------------------------
        */
        $pro = Plan::create([
            'name'        => 'Pro',
            'code'        => 'pro',
            'price_usd'   => 5.00,
            'price_khr'   => 20000,
            'seats'       => 5,
            'storage_gb'  => 10,
            'api_limit'   => 10000,
            'is_active'   => true,
        ]);

        $this->createBillingCycles($pro, [
            ['label' => 'Monthly', 'months' => 1,  'discount_percent' => 0,  'is_active' => true],
            ['label' => 'Quarterly', 'months' => 3, 'discount_percent' => 8, 'is_active' => true],
            ['label' => 'Yearly',    'months' => 12,'discount_percent' => 20,'is_active' => true],
        ]);

        $this->createFeatures($pro, [
            ['key' => 'unlimited_products', 'en' => 'Unlimited products',       'km' => 'ផលិតផលគ្មានដែនកំណត់'],
            ['key' => 'branches_limit',     'en' => 'Up to 5 branches',         'km' => 'អាចប្រើបាន 5 សាខា'],
            ['key' => 'staff_limit',        'en' => 'Up to 5 staff accounts',   'km' => 'គណនីបុគ្គលិករហូតដល់ 5'],
            ['key' => 'advanced_reports',   'en' => 'Advanced sales reports',   'km' => 'របាយការណ៍លក់កម្រិតខ្ពស់'],
            ['key' => 'inventory',          'en' => 'Inventory management',     'km' => 'គ្រប់គ្រងស្តុក'],
            ['key' => 'qr_menu',            'en' => 'Digital menu (QR ordering)','km' => 'ម៉ឺនុយឌីជីថល (បញ្ជាទិញតាម QR)'],
        ]);

        /*
        |--------------------------------------------------------------------------
        | ENTERPRISE PLAN
        |--------------------------------------------------------------------------
        */
        $enterprise = Plan::create([
            'name'        => 'Enterprise',
            'code'        => 'enterprise',
            'price_usd'   => 7.00,
            'price_khr'   => 28000,
            'seats'       => 999,
            'storage_gb'  => 100,
            'api_limit'   => 100000,
            'is_active'   => true,
        ]);

        $this->createBillingCycles($enterprise, [
            ['label' => 'Monthly',  'months' => 1,  'discount_percent' => 0,  'is_active' => true],
            ['label' => 'Quarterly','months' => 3,  'discount_percent' => 10, 'is_active' => true],
            ['label' => 'Yearly',   'months' => 12, 'discount_percent' => 25, 'is_active' => true],
        ]);

        $this->createFeatures($enterprise, [
            ['key' => 'unlimited_products','en' => 'Unlimited products',         'km' => 'ផលិតផលគ្មានដែនកំណត់'],
            ['key' => 'unlimited_branches','en' => 'Unlimited branches',         'km' => 'សាខាគ្មានដែនកំណត់'],
            ['key' => 'unlimited_staff',   'en' => 'Unlimited staff accounts',   'km' => 'គណនីបុគ្គលិកគ្មានដែនកំណត់'],
            ['key' => 'custom_reports',    'en' => 'Advanced + custom reports',  'km' => 'របាយការណ៍កម្រិតខ្ពស់ + ផ្ទាល់ខ្លួន'],
            ['key' => 'inventory',         'en' => 'Inventory management',       'km' => 'គ្រប់គ្រងស្តុក'],
            ['key' => 'qr_menu',           'en' => 'Digital menu (QR ordering)', 'km' => 'ម៉ឺនុយឌីជីថល (បញ្ជាទិញតាម QR)'],
            ['key' => 'priority_support',  'en' => 'Priority support',           'km' => 'ជំនួយអាទិភាព'],
        ]);
    }

    /**
     * Create billing cycles for a plan
     */
    private function createBillingCycles(Plan $plan, array $cycles): void
    {
        foreach ($cycles as $cycle) {
            PlanBillingCycle::create([
                'plan_id'          => $plan->id,
                'label'            => $cycle['label'],
                'months'           => $cycle['months'],
                'discount_percent' => $cycle['discount_percent'],
                'is_active'        => $cycle['is_active'],
            ]);
        }
    }

    /**
     * Create features for a plan
     */
    private function createFeatures(Plan $plan, array $features): void
    {
        foreach ($features as $index => $feature) {
            PlanFeature::create([
                'plan_id'    => $plan->id,
                'key'        => $feature['key'],
                'en'         => $feature['en'],
                'km'         => $feature['km'],
                'sort_order' => $index + 1,
            ]);
        }
    }
}
