<?php

namespace Database\Seeders;

use App\Models\Competency;
use App\Models\Course;
use Illuminate\Database\Seeder;

class CompetenciasPrimariaSeeder extends Seeder
{
    public function run(): void
    {
        // Catálogo inicial de competencias del Currículo Nacional (MINEDU).
        // Las capacidades, desempeños y criterios se concretan en la programación de cada grado.
        // Fuente: https://www.minedu.gob.pe/curriculo/pdf/curriculo-nacional-2016-2.pdf
        $competenciasPorCurso = [
            'Comunicación' => [
                'Se comunica oralmente en su lengua materna',
                'Lee diversos tipos de textos escritos en su lengua materna',
                'Escribe diversos tipos de textos en su lengua materna',
            ],
            'Matemática' => [
                'Resuelve problemas de cantidad',
                'Resuelve problemas de regularidad, equivalencia y cambio',
                'Resuelve problemas de forma, movimiento y localización',
                'Resuelve problemas de gestión de datos e incertidumbre',
            ],
            'Ciencia y Tecnología' => [
                'Indaga mediante métodos científicos para construir sus conocimientos',
                'Explica el mundo físico basándose en conocimientos sobre los seres vivos, materia y energía, biodiversidad, Tierra y universo',
                'Diseña y construye soluciones tecnológicas para resolver problemas de su entorno',
            ],
            'Personal Social' => [
                'Construye su identidad',
                'Convive y participa democráticamente en la búsqueda del bien común',
                'Construye interpretaciones históricas',
                'Gestiona responsablemente el espacio y el ambiente',
                'Gestiona responsablemente los recursos económicos',
            ],
            'Educación Física' => [
                'Se desenvuelve de manera autónoma a través de su motricidad',
                'Asume una vida saludable',
                'Interactúa a través de sus habilidades sociomotrices',
            ],
            'Arte y Cultura' => [
                'Aprecia de manera crítica manifestaciones artístico-culturales',
                'Crea proyectos desde los lenguajes artísticos',
            ],
            'Inglés' => [
                'Se comunica oralmente en inglés como lengua extranjera',
                'Lee diversos tipos de textos escritos en inglés como lengua extranjera',
                'Escribe diversos tipos de textos en inglés como lengua extranjera',
            ],
            'Educación Religiosa' => [
                'Construye su identidad como persona humana, amada por Dios, digna, libre y trascendente, comprendiendo la doctrina de su propia religión, abierto al diálogo con las que le son cercanas',
                'Asume la experiencia del encuentro personal y comunitario con Dios en su proyecto de vida en coherencia con su creencia religiosa',
            ],
        ];

        foreach ($competenciasPorCurso as $nombreCurso => $nombres) {
            $curso = Course::query()->where('name', $nombreCurso)->first();
            if (! $curso) {
                continue;
            }

            foreach ($nombres as $nombre) {
                Competency::query()->firstOrCreate(
                    ['course_id' => $curso->id, 'grade' => null, 'name' => $nombre],
                    [
                        'capacity' => 'Por definir en la programación curricular del grado.',
                        'performance' => 'Por definir en la programación curricular del grado.',
                        'evaluation_criterion' => 'Por definir por la docente para cada evaluación.',
                        'status' => 'Activo',
                    ]
                );
            }
        }
    }
}
