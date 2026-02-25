<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class EmployeeSeeder extends Seeder
{
    public function run(): void
    {
        $now = Carbon::now();

        $c1 = '11111111-1111-1111-1111-111111111101';
        $c2 = '11111111-1111-1111-1111-111111111102';
        $c3 = '11111111-1111-1111-1111-111111111103';
        $c4 = '11111111-1111-1111-1111-111111111104';

        $s1 = '22222222-2222-2222-2222-222222222201';
        $s2 = '22222222-2222-2222-2222-222222222202';
        $s3 = '22222222-2222-2222-2222-222222222203';
        $s4 = '22222222-2222-2222-2222-222222222204';
        $s5 = '22222222-2222-2222-2222-222222222205';
        $s6 = '22222222-2222-2222-2222-222222222206';

        $d1 = '33333333-3333-3333-3333-333333333301';
        $d2 = '33333333-3333-3333-3333-333333333302';
        $d3 = '33333333-3333-3333-3333-333333333303';
        $d4 = '33333333-3333-3333-3333-333333333304';
        $d5 = '33333333-3333-3333-3333-333333333305';
        $d6 = '33333333-3333-3333-3333-333333333306';
        $d7 = '33333333-3333-3333-3333-333333333307';
        $d8 = '33333333-3333-3333-3333-333333333308';
        $d9 = '33333333-3333-3333-3333-333333333309';

        $employees = [
            ['id' => '44444444-4444-4444-4444-444444444401', 'company_id' => $c1, 'site_id' => $s1, 'department_id' => $d1, 'first_name' => 'Moussa', 'last_name' => 'Ouedraogo', 'email' => 'moussa.ouedraogo@orange.bf', 'phone' => '+226 70 00 00 01', 'position' => 'Directeur General', 'employee_number' => 'EMP-001', 'is_active' => true, 'hire_date' => '2022-01-15', 'biometric_enrolled' => true, 'created_at' => $now, 'updated_at' => $now],
            ['id' => '44444444-4444-4444-4444-444444444402', 'company_id' => $c1, 'site_id' => $s1, 'department_id' => $d2, 'first_name' => 'Salamata', 'last_name' => 'Kabore', 'email' => 'salamata.kabore@orange.bf', 'phone' => '+226 70 00 00 02', 'position' => 'Responsable RH', 'employee_number' => 'EMP-002', 'is_active' => true, 'hire_date' => '2022-03-01', 'biometric_enrolled' => true, 'created_at' => $now, 'updated_at' => $now],
            ['id' => '44444444-4444-4444-4444-444444444403', 'company_id' => $c1, 'site_id' => $s1, 'department_id' => $d3, 'first_name' => 'Abdoulaye', 'last_name' => 'Sanou', 'email' => 'abdoulaye.sanou@orange.bf', 'phone' => '+226 70 00 00 03', 'position' => 'Ingenieur Reseau', 'employee_number' => 'EMP-003', 'is_active' => true, 'hire_date' => '2022-06-15', 'biometric_enrolled' => false, 'created_at' => $now, 'updated_at' => $now],
            ['id' => '44444444-4444-4444-4444-444444444404', 'company_id' => $c1, 'site_id' => $s1, 'department_id' => $d3, 'first_name' => 'Mariam', 'last_name' => 'Zongo', 'email' => 'mariam.zongo@orange.bf', 'phone' => '+226 70 00 00 04', 'position' => 'Technicienne', 'employee_number' => 'EMP-004', 'is_active' => true, 'hire_date' => '2023-01-10', 'biometric_enrolled' => true, 'created_at' => $now, 'updated_at' => $now],
            ['id' => '44444444-4444-4444-4444-444444444405', 'company_id' => $c1, 'site_id' => $s2, 'department_id' => $d4, 'first_name' => 'Boureima', 'last_name' => 'Compaore', 'email' => 'boureima.compaore@orange.bf', 'phone' => '+226 70 00 00 05', 'position' => 'Commercial', 'employee_number' => 'EMP-005', 'is_active' => true, 'hire_date' => '2023-04-01', 'biometric_enrolled' => false, 'created_at' => $now, 'updated_at' => $now],
            ['id' => '44444444-4444-4444-4444-444444444406', 'company_id' => $c1, 'site_id' => $s2, 'department_id' => $d4, 'first_name' => 'Rasmata', 'last_name' => 'Tiendrebeogo', 'email' => 'rasmata.tiendrebeogo@orange.bf', 'phone' => '+226 70 00 00 06', 'position' => 'Assistante Commerciale', 'employee_number' => 'EMP-006', 'is_active' => false, 'hire_date' => '2023-07-15', 'biometric_enrolled' => false, 'created_at' => $now, 'updated_at' => $now],
            ['id' => '44444444-4444-4444-4444-444444444407', 'company_id' => $c2, 'site_id' => $s3, 'department_id' => $d5, 'first_name' => 'Idrissa', 'last_name' => 'Sawadogo', 'email' => 'idrissa.sawadogo@corisbank.bf', 'phone' => '+226 70 00 00 07', 'position' => 'Directeur Adjoint', 'employee_number' => 'EMP-007', 'is_active' => true, 'hire_date' => '2022-02-01', 'biometric_enrolled' => true, 'created_at' => $now, 'updated_at' => $now],
            ['id' => '44444444-4444-4444-4444-444444444408', 'company_id' => $c2, 'site_id' => $s3, 'department_id' => $d6, 'first_name' => 'Fati', 'last_name' => 'Diallo', 'email' => 'fati.diallo@corisbank.bf', 'phone' => '+226 70 00 00 08', 'position' => 'Responsable Clientele', 'employee_number' => 'EMP-008', 'is_active' => true, 'hire_date' => '2022-05-20', 'biometric_enrolled' => false, 'created_at' => $now, 'updated_at' => $now],
            ['id' => '44444444-4444-4444-4444-444444444409', 'company_id' => $c2, 'site_id' => $s3, 'department_id' => $d6, 'first_name' => 'Hamidou', 'last_name' => 'Barry', 'email' => 'hamidou.barry@corisbank.bf', 'phone' => '+226 70 00 00 09', 'position' => 'Conseiller Client', 'employee_number' => 'EMP-009', 'is_active' => true, 'hire_date' => '2023-02-15', 'biometric_enrolled' => false, 'created_at' => $now, 'updated_at' => $now],
            ['id' => '44444444-4444-4444-4444-444444444410', 'company_id' => $c2, 'site_id' => $s4, 'department_id' => $d7, 'first_name' => 'Zenabo', 'last_name' => 'Coulibaly', 'email' => 'zenabo.coulibaly@corisbank.bf', 'phone' => '+226 70 00 00 10', 'position' => 'Chef Operations', 'employee_number' => 'EMP-010', 'is_active' => true, 'hire_date' => '2022-09-01', 'biometric_enrolled' => true, 'created_at' => $now, 'updated_at' => $now],
            ['id' => '44444444-4444-4444-4444-444444444411', 'company_id' => $c3, 'site_id' => $s5, 'department_id' => $d8, 'first_name' => 'Adama', 'last_name' => 'Sorgho', 'email' => 'adama.sorgho@onatel.bf', 'phone' => '+226 70 00 00 11', 'position' => 'Directeur Technique', 'employee_number' => 'EMP-011', 'is_active' => true, 'hire_date' => '2022-04-15', 'biometric_enrolled' => false, 'created_at' => $now, 'updated_at' => $now],
            ['id' => '44444444-4444-4444-4444-444444444412', 'company_id' => $c3, 'site_id' => $s5, 'department_id' => $d8, 'first_name' => 'Kadidia', 'last_name' => 'Pale', 'email' => 'kadidia.pale@onatel.bf', 'phone' => '+226 70 00 00 12', 'position' => 'Secretaire', 'employee_number' => 'EMP-012', 'is_active' => true, 'hire_date' => '2023-08-01', 'biometric_enrolled' => false, 'created_at' => $now, 'updated_at' => $now],
            ['id' => '44444444-4444-4444-4444-444444444413', 'company_id' => $c4, 'site_id' => $s6, 'department_id' => $d9, 'first_name' => 'Seydou', 'last_name' => 'Tapsoba', 'email' => 'seydou.tapsoba@sonabel.bf', 'phone' => '+226 70 00 00 13', 'position' => 'Ingenieur Electrique', 'employee_number' => 'EMP-013', 'is_active' => true, 'hire_date' => '2022-11-01', 'biometric_enrolled' => false, 'created_at' => $now, 'updated_at' => $now],
            ['id' => '44444444-4444-4444-4444-444444444414', 'company_id' => $c4, 'site_id' => $s6, 'department_id' => $d9, 'first_name' => 'Bibata', 'last_name' => 'Ilboudo', 'email' => 'bibata.ilboudo@sonabel.bf', 'phone' => '+226 70 00 00 14', 'position' => 'Comptable', 'employee_number' => 'EMP-014', 'is_active' => true, 'hire_date' => '2024-01-15', 'biometric_enrolled' => false, 'created_at' => $now, 'updated_at' => $now],
        ];

        DB::table('employees')->insert($employees);
    }
}
