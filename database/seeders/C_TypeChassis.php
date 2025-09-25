<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class C_TypeChassis extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('c_type_chassis')->insert([
            //mitshubisi
            [
                'id' => '0101001',
                'type_chassis' => 'COLT DIESEL FE 71 (4X2)',
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'id' => '0101002',
                'type_chassis' => 'COLT DIESEL FE 71 PS (4X2)',
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'id' => '0101003',
                'type_chassis' => 'COLT DIESEL FE 71 L (4X2)',
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'id' => '0101004',
                'type_chassis' => 'COLT DIESEL FE 73 (4X2)',
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'id' => '0101005',
                'type_chassis' => 'COLT DIESEL FE 73 HD (4X2)',
                'created_at' => now(),
                'updated_at' => now()
            ],
            //Hino
            [
                'id' => '0102001',
                'type_chassis' => 'FC9JNKA-NNJ (4X2) M/T',
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'id' => '0102002',
                'type_chassis' => 'FG8JJ1D-BGJ (FG 235 JJ)',
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'id' => '0102003',
                'type_chassis' => 'FG8JJ1D-JGJ (FG 245 JJ)',
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'id' => '0102004',
                'type_chassis' => 'FG8JK1A-BGJ (FG 235 JK)',
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'id' => '0102005',
                'type_chassis' => 'FG8JK1A-JGJ (FG 245 JK)',
                'created_at' => now(),
                'updated_at' => now()
            ],
            //isuzu : NMR 71T SD (4X2) NMR 71T HD 5.8 (4X2) NMR 71T HD 6.1 (4X2) NMR 71T HD 6.5 (4X2) NMR 71T SD L (4x2)
            [
                'id' => '0103001',
                'type_chassis' => 'NMR 71T SD (4X2)',
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'id' => '0103002',
                'type_chassis' => 'NMR 71T HD 5.8 (4X2)',
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'id' => '0103003',
                'type_chassis' => 'NMR 71T HD 6.1 (4X2)',
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'id' => '0103004',
                'type_chassis' => 'NMR 71T HD 6.5 (4X2)',
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'id' => '0103005',
                'type_chassis' => 'NMR 71T SD L (4x2)',
                'created_at' => now(),
                'updated_at' => now()
            ],
            //UD Trucks CKE 250 4X2R WB4600MM CKE 250 4X2R WB5200MM CKE 250 4X2R WB6000MM CKE 250 6X2R WB6000MM CDE 250 6X2R WB5100MM
            [
                'id' => '0104001',
                'type_chassis' => 'CKE 250 4X2R WB4600MM',
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'id' => '0104002',
                'type_chassis' => 'CKE 250 4X2R WB5200MM',
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'id' => '0104003',
                'type_chassis' => 'CKE 250 4X2R WB6000MM',
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'id' => '0104004',
                'type_chassis' => 'CKE 250 6X2R WB6000MM',
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'id' => '0104005',
                'type_chassis' => 'CDE 250 6X2R WB5100MM',
                'created_at' => now(),
                'updated_at' => now()
            ],
            //Mercedes Benz : MERCEDEZ-BENZ 1623C (4X2) MERCEDEZ-BENZ 1623 R/51 (4X2) MERCEDEZ-BENZ 1623 R/60 (4X2) MERCEDEZ-BENZ 2523 R/45 6X2) MERCEDEZ-BENZ 2528 R (6X2)
            [
                'id' => '0105001',
                'type_chassis' => 'MERCEDEZ-BENZ 1623C (4X2)',
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'id' => '0105002',
                'type_chassis' => 'MERCEDEZ-BENZ 1623 R/51 (4X2)',
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'id' => '0105003',
                'type_chassis' => 'MERCEDEZ-BENZ 1623 R/60 (4X2)',
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'id' => '0105004',
                'type_chassis' => 'MERCEDEZ-BENZ 2523 R/45 (6X2)',
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'id' => '0105005',
                'type_chassis' => 'MERCEDEZ-BENZ 2528 R (6X2)',
                'created_at' => now(),
                'updated_at' => now()
            ],
        ]);
    }
}
