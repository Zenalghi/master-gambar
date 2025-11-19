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
                'id' => '1',
                'type_chassis' => 'COLT DIESEL FE 71 (4X2)',
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'id' => '2',
                'type_chassis' => 'COLT DIESEL FE 71 PS (4X2)',
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'id' => '3',
                'type_chassis' => 'COLT DIESEL FE 71 L (4X2)',
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'id' => '4',
                'type_chassis' => 'COLT DIESEL FE 73 (4X2)',
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'id' => '5',
                'type_chassis' => 'COLT DIESEL FE 73 HD (4X2)',
                'created_at' => now(),
                'updated_at' => now()
            ],
            //Hino
            [
                'id' => '6',
                'type_chassis' => 'FC9JNKA-NNJ (4X2) M/T',
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'id' => '7',
                'type_chassis' => 'FG8JJ1D-BGJ (FG 235 JJ)',
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'id' => '8',
                'type_chassis' => 'FG8JJ1D-JGJ (FG 245 JJ)',
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'id' => '9',
                'type_chassis' => 'FG8JK1A-BGJ (FG 235 JK)',
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'id' => '10',
                'type_chassis' => 'FG8JK1A-JGJ (FG 245 JK)',
                'created_at' => now(),
                'updated_at' => now()
            ],
            //isuzu : NMR 71T SD (4X2) NMR 71T HD 5.8 (4X2) NMR 71T HD 6.1 (4X2) NMR 71T HD 6.5 (4X2) NMR 71T SD L (4x2)
            [
                'id' => '11',
                'type_chassis' => 'NMR 71T SD (4X2)',
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'id' => '12',
                'type_chassis' => 'NMR 71T HD 5.8 (4X2)',
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'id' => '13',
                'type_chassis' => 'NMR 71T HD 6.1 (4X2)',
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'id' => '14',
                'type_chassis' => 'NMR 71T HD 6.5 (4X2)',
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'id' => '15',
                'type_chassis' => 'NMR 71T SD L (4x2)',
                'created_at' => now(),
                'updated_at' => now()
            ],
            //UD Trucks CKE 250 4X2R WB4600MM CKE 250 4X2R WB5200MM CKE 250 4X2R WB6000MM CKE 250 6X2R WB6000MM CDE 250 6X2R WB5100MM
            [
                'id' => '16',
                'type_chassis' => 'CKE 250 4X2R WB4600MM',
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'id' => '17',
                'type_chassis' => 'CKE 250 4X2R WB5200MM',
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'id' => '18',
                'type_chassis' => 'CKE 250 4X2R WB6000MM',
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'id' => '19',
                'type_chassis' => 'CKE 250 6X2R WB6000MM',
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'id' => '20',
                'type_chassis' => 'CDE 250 6X2R WB5100MM',
                'created_at' => now(),
                'updated_at' => now()
            ],
            //Mercedes Benz : MERCEDEZ-BENZ 1623C (4X2) MERCEDEZ-BENZ 1623 R/51 (4X2) MERCEDEZ-BENZ 1623 R/60 (4X2) MERCEDEZ-BENZ 2523 R/45 6X2) MERCEDEZ-BENZ 2528 R (6X2)
            [
                'id' => '21',
                'type_chassis' => 'MERCEDEZ-BENZ 1623C (4X2)',
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'id' => '22',
                'type_chassis' => 'MERCEDEZ-BENZ 1623 R/51 (4X2)',
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'id' => '23',
                'type_chassis' => 'MERCEDEZ-BENZ 1623 R/60 (4X2)',
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'id' => '24',
                'type_chassis' => 'MERCEDEZ-BENZ 2523 R/45 (6X2)',
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'id' => '25',
                'type_chassis' => 'MERCEDEZ-BENZ 2528 R (6X2)',
                'created_at' => now(),
                'updated_at' => now()
            ],
        ]);
    }
}
