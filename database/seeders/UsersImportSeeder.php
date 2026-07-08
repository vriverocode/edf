<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class UsersImportSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run()
    {

        // 1. Cargas el array desde el archivo que te acabo de generar
        // (Asegúrate de colocar el archivo 'array_usuarios.php' en una ruta accesible, por ejemplo en database/data/ o en el mismo seeder)
        $usuarios = [
            ['name' => 'Gloria Cisneros', 'username' => 'gcisneros', 'password' => 'Gcisneros2026*', 'rol_id' => 7, 'is_first_time' => 1],
            ['name' => 'Rolando Gomez', 'username' => 'rgomez', 'password' => 'Rgomez2026*', 'rol_id' => 7, 'is_first_time' => 1],
            ['name' => 'Teobaldo Rejas', 'username' => 'trejas', 'password' => 'Trejas2026*', 'rol_id' => 7, 'is_first_time' => 1],
            ['name' => 'Carlos Villavicencio', 'username' => 'cvillavicencio', 'password' => 'Cvillavicencio2026*', 'rol_id' => 7, 'is_first_time' => 1],
            ['name' => 'Mirley Avendaño', 'username' => 'mavendano', 'password' => 'Mavendano2026*', 'rol_id' => 7, 'is_first_time' => 1],
            ['name' => 'Antonio Avila', 'username' => 'aavila', 'password' => 'Aavila2026*', 'rol_id' => 7, 'is_first_time' => 1],
            ['name' => 'Juan Calderon', 'username' => 'jcalderon', 'password' => 'Jcalderon2026*', 'rol_id' => 7, 'is_first_time' => 1],
            ['name' => 'Nilton Rios', 'username' => 'nrios', 'password' => 'Nrios2026*', 'rol_id' => 7, 'is_first_time' => 1],
            ['name' => 'Victor Rojas', 'username' => 'vrojas', 'password' => 'Vrojas2026*', 'rol_id' => 7, 'is_first_time' => 1],
            ['name' => 'Rosa Gavidia', 'username' => 'rgavidia', 'password' => 'Rgavidia2026*', 'rol_id' => 7, 'is_first_time' => 1],
            ['name' => 'Hernan Aquije', 'username' => 'haquije', 'password' => 'Haquije2026*', 'rol_id' => 7, 'is_first_time' => 1],
            ['name' => 'Jose Navarro', 'username' => 'jnavarro', 'password' => 'Jnavarro2026*', 'rol_id' => 7, 'is_first_time' => 1],
        ];

        foreach ($usuarios as $usuario) {
            User::create([
                'name' => $usuario['name'],
                'username' => $usuario['username'],
                'password' => bcrypt($usuario['password']), // <-- Aquí encriptas usando Laravel
                'rol_id' => $usuario['rol_id'],
                'email' => null,
                'status' => 1,
                'is_first_time' => $usuario['is_first_time'],
            ]);
        }

        $this->command->info('Usuarios importados correctamente.');
    }
}
