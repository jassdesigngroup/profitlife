<?php

namespace Database\Seeders;

use App\Domain\Training\Models\Equipment;
use App\Domain\Training\Models\Exercise;
use App\Domain\Training\Models\MuscleGroup;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Biblioteca base de ejercicios. Idempotente: solo crea lo que no existe
 * (por slug) y no toca lo que el centro haya editado.
 */
class ExerciseLibrarySeeder extends Seeder
{
    private const GROUPS = [
        'Pecho', 'Espalda', 'Hombros', 'Bíceps', 'Tríceps', 'Antebrazo', 'Abdomen', 'Zona lumbar', 'Glúteos',
        'Cuádriceps', 'Isquiotibiales', 'Aductores', 'Pantorrillas', 'Cuerpo completo', 'Cardiovascular', 'Movilidad',
    ];

    private const EQUIPMENT = [
        'Peso corporal', 'Barra', 'Mancuernas', 'Kettlebell', 'Máquina', 'Polea', 'Banda elástica', 'Banco',
        'Balón medicinal', 'TRX', 'Cuerda', 'Caminadora', 'Bicicleta estática', 'Remo ergómetro', 'Fitball', 'Barra fija',
    ];

    /**
     * nombre => [principales, secundarios, equipo, instrucciones]
     *
     * @return array<string, array{0: list<string>, 1: list<string>, 2: list<string>, 3: string}>
     */
    private function exercises(): array
    {
        return [
            'Sentadilla con barra' => [['Cuádriceps', 'Glúteos'], ['Isquiotibiales', 'Zona lumbar'], ['Barra'], 'Pies al ancho de hombros, espalda neutra; baja hasta que los muslos queden paralelos y sube empujando el piso.'],
            'Sentadilla goblet' => [['Cuádriceps', 'Glúteos'], ['Abdomen'], ['Kettlebell', 'Mancuernas'], 'Sostén la pesa contra el pecho y baja manteniendo el tronco erguido.'],
            'Sentadilla con peso corporal' => [['Cuádriceps', 'Glúteos'], [], ['Peso corporal'], 'Baja controlado con los brazos al frente y las rodillas alineadas con los pies.'],
            'Sentadilla búlgara' => [['Cuádriceps', 'Glúteos'], ['Aductores'], ['Mancuernas', 'Banco'], 'Pie trasero sobre el banco; baja la rodilla trasera hacia el piso con el tronco estable.'],
            'Prensa de piernas' => [['Cuádriceps', 'Glúteos'], ['Isquiotibiales'], ['Máquina'], 'Baja la plataforma sin despegar la zona lumbar del respaldo y empuja con todo el pie.'],
            'Zancada caminando' => [['Cuádriceps', 'Glúteos'], ['Isquiotibiales'], ['Mancuernas', 'Peso corporal'], 'Da pasos largos bajando la rodilla trasera cerca del piso, alternando piernas.'],
            'Zancada inversa' => [['Glúteos', 'Cuádriceps'], [], ['Mancuernas', 'Peso corporal'], 'Da un paso atrás y baja hasta 90 grados en ambas rodillas.'],
            'Step-up al cajón' => [['Cuádriceps', 'Glúteos'], [], ['Mancuernas', 'Banco'], 'Sube al cajón empujando con la pierna de arriba, sin impulsarte con la de abajo.'],
            'Peso muerto convencional' => [['Isquiotibiales', 'Glúteos', 'Zona lumbar'], ['Espalda', 'Antebrazo'], ['Barra'], 'Barra pegada a las piernas, espalda neutra; extiende cadera y rodillas a la vez.'],
            'Peso muerto rumano' => [['Isquiotibiales', 'Glúteos'], ['Zona lumbar'], ['Barra', 'Mancuernas'], 'Rodillas ligeramente flexionadas; lleva la cadera atrás hasta sentir el estiramiento posterior.'],
            'Hip thrust' => [['Glúteos'], ['Isquiotibiales'], ['Barra', 'Banco'], 'Espalda alta en el banco; eleva la cadera hasta alinear tronco y muslos y aprieta glúteos.'],
            'Puente de glúteo' => [['Glúteos'], ['Isquiotibiales'], ['Peso corporal'], 'Acostado boca arriba, rodillas flexionadas; eleva la cadera y mantén un segundo arriba.'],
            'Curl femoral acostado' => [['Isquiotibiales'], [], ['Máquina'], 'Flexiona las rodillas llevando los talones hacia los glúteos sin despegar la cadera.'],
            'Extensión de cuádriceps' => [['Cuádriceps'], [], ['Máquina'], 'Extiende las rodillas por completo y baja de forma controlada.'],
            'Aducción en máquina' => [['Aductores'], [], ['Máquina'], 'Cierra las piernas contra la resistencia sin impulso.'],
            'Abducción con banda' => [['Glúteos'], [], ['Banda elástica'], 'Con la banda sobre las rodillas, abre las piernas manteniendo la cadera estable.'],
            'Elevación de talones de pie' => [['Pantorrillas'], [], ['Máquina', 'Mancuernas'], 'Sube en puntas de pie lo más alto posible y baja lento.'],
            'Swing con kettlebell' => [['Glúteos', 'Isquiotibiales'], ['Zona lumbar', 'Hombros'], ['Kettlebell'], 'Bisagra de cadera explosiva; la pesa sube por el impulso de la cadera, no de los brazos.'],
            'Press de banca con barra' => [['Pecho'], ['Tríceps', 'Hombros'], ['Barra', 'Banco'], 'Baja la barra al pecho con codos a unos 45 grados y empuja hasta extender.'],
            'Press de banca con mancuernas' => [['Pecho'], ['Tríceps', 'Hombros'], ['Mancuernas', 'Banco'], 'Baja las mancuernas a los lados del pecho y empuja juntándolas arriba.'],
            'Press inclinado con mancuernas' => [['Pecho'], ['Hombros', 'Tríceps'], ['Mancuernas', 'Banco'], 'Banco a 30–45 grados; empuja en línea con la parte alta del pecho.'],
            'Aperturas con mancuernas' => [['Pecho'], ['Hombros'], ['Mancuernas', 'Banco'], 'Codos ligeramente flexionados; abre los brazos en arco y vuelve apretando el pecho.'],
            'Cruce de poleas' => [['Pecho'], ['Hombros'], ['Polea'], 'Lleva las manos al frente y abajo cruzándolas ligeramente.'],
            'Flexiones de pecho' => [['Pecho'], ['Tríceps', 'Hombros', 'Abdomen'], ['Peso corporal'], 'Cuerpo alineado; baja el pecho cerca del piso y empuja.'],
            'Fondos en paralelas' => [['Tríceps', 'Pecho'], ['Hombros'], ['Peso corporal'], 'Baja hasta 90 grados de codo con el tronco ligeramente inclinado.'],
            'Dominadas' => [['Espalda'], ['Bíceps', 'Antebrazo'], ['Barra fija'], 'Agarre prono; sube hasta pasar la barbilla sobre la barra y baja controlado.'],
            'Jalón al pecho' => [['Espalda'], ['Bíceps'], ['Polea'], 'Lleva la barra a la parte alta del pecho juntando escápulas.'],
            'Remo con barra' => [['Espalda'], ['Bíceps', 'Zona lumbar'], ['Barra'], 'Tronco inclinado y espalda neutra; lleva la barra hacia el abdomen.'],
            'Remo con mancuerna a una mano' => [['Espalda'], ['Bíceps'], ['Mancuernas', 'Banco'], 'Apoya rodilla y mano en el banco; lleva la mancuerna hacia la cadera.'],
            'Remo sentado en polea' => [['Espalda'], ['Bíceps'], ['Polea'], 'Pecho arriba; tira hacia el abdomen juntando las escápulas.'],
            'Remo invertido en TRX' => [['Espalda'], ['Bíceps', 'Abdomen'], ['TRX'], 'Cuerpo recto inclinado; tira del pecho hacia las manos.'],
            'Face pull' => [['Hombros', 'Espalda'], [], ['Polea', 'Cuerda'], 'Tira de la cuerda hacia la cara separando las manos y rotando hacia afuera.'],
            'Press militar con barra' => [['Hombros'], ['Tríceps'], ['Barra'], 'De pie, abdomen firme; empuja la barra por encima de la cabeza.'],
            'Press de hombros con mancuernas' => [['Hombros'], ['Tríceps'], ['Mancuernas', 'Banco'], 'Empuja las mancuernas desde la altura de las orejas hasta extender.'],
            'Elevaciones laterales' => [['Hombros'], [], ['Mancuernas'], 'Sube los brazos a los lados hasta la altura de los hombros.'],
            'Pájaros (vuelos posteriores)' => [['Hombros'], ['Espalda'], ['Mancuernas'], 'Tronco inclinado; abre los brazos hacia los lados con codos ligeramente flexionados.'],
            'Rotación externa con banda' => [['Hombros'], [], ['Banda elástica'], 'Codo pegado al costado a 90 grados; rota el antebrazo hacia afuera.'],
            'Curl de bíceps con barra' => [['Bíceps'], ['Antebrazo'], ['Barra'], 'Codos fijos al costado; flexiona sin balancear el tronco.'],
            'Curl martillo' => [['Bíceps', 'Antebrazo'], [], ['Mancuernas'], 'Agarre neutro; flexiona alternando brazos.'],
            'Extensión de tríceps en polea' => [['Tríceps'], [], ['Polea', 'Cuerda'], 'Codos fijos; extiende hacia abajo separando la cuerda al final.'],
            'Press francés' => [['Tríceps'], [], ['Barra', 'Banco'], 'Acostado, baja la barra hacia la frente flexionando solo los codos.'],
            'Plancha frontal' => [['Abdomen'], ['Hombros', 'Glúteos'], ['Peso corporal'], 'Antebrazos en el piso, cuerpo alineado; mantén sin hundir la cadera.'],
            'Plancha lateral' => [['Abdomen'], ['Glúteos'], ['Peso corporal'], 'De lado sobre el antebrazo, cadera arriba y cuerpo alineado.'],
            'Dead bug' => [['Abdomen'], [], ['Peso corporal'], 'Boca arriba; extiende brazo y pierna opuestos manteniendo la zona lumbar pegada al piso.'],
            'Bird dog' => [['Zona lumbar', 'Abdomen'], ['Glúteos'], ['Peso corporal'], 'En cuatro apoyos; extiende brazo y pierna opuestos sin rotar la cadera.'],
            'Pallof press' => [['Abdomen'], [], ['Polea', 'Banda elástica'], 'De lado a la polea; empuja al frente resistiendo la rotación.'],
            'Crunch abdominal' => [['Abdomen'], [], ['Peso corporal'], 'Eleva los hombros del piso contrayendo el abdomen, sin jalar el cuello.'],
            'Elevación de piernas colgado' => [['Abdomen'], [], ['Barra fija'], 'Colgado de la barra, eleva las piernas sin balanceo.'],
            'Russian twist' => [['Abdomen'], [], ['Balón medicinal'], 'Sentado con el tronco inclinado, rota llevando el balón de lado a lado.'],
            'Superman' => [['Zona lumbar'], ['Glúteos'], ['Peso corporal'], 'Boca abajo; eleva brazos y piernas a la vez y sostén un segundo.'],
            'Burpees' => [['Cuerpo completo', 'Cardiovascular'], [], ['Peso corporal'], 'Sentadilla, apoyo de manos, salto atrás a plancha, regreso y salto vertical.'],
            'Thruster con mancuernas' => [['Cuerpo completo'], ['Cuádriceps', 'Hombros'], ['Mancuernas'], 'Sentadilla frontal seguida de press por encima de la cabeza en un solo movimiento.'],
            'Lanzamiento de balón medicinal' => [['Cuerpo completo'], ['Abdomen'], ['Balón medicinal'], 'Lanza el balón contra el piso o la pared con fuerza desde el tronco.'],
            'Saltos de tijera' => [['Cardiovascular'], [], ['Peso corporal'], 'Abre y cierra brazos y piernas saltando a ritmo constante.'],
            'Salto a la cuerda' => [['Cardiovascular'], ['Pantorrillas'], ['Cuerda'], 'Saltos cortos sobre la punta de los pies, girando la cuerda con las muñecas.'],
            'Trote en caminadora' => [['Cardiovascular'], [], ['Caminadora'], 'Ritmo constante y conversacional salvo que se indique otra intensidad.'],
            'Bicicleta estática' => [['Cardiovascular'], ['Cuádriceps'], ['Bicicleta estática'], 'Ajusta el sillín a la altura de la cadera y pedalea a la cadencia indicada.'],
            'Remo ergómetro' => [['Cardiovascular', 'Espalda'], ['Cuádriceps'], ['Remo ergómetro'], 'Empuja con piernas, luego tronco y por último brazos; regresa en orden inverso.'],
            'Movilidad de cadera 90/90' => [['Movilidad'], ['Glúteos'], ['Peso corporal'], 'Sentado con ambas piernas a 90 grados; rota las rodillas de un lado al otro.'],
            'Estiramiento de isquiotibiales' => [['Movilidad'], ['Isquiotibiales'], ['Peso corporal', 'Banda elástica'], 'Pierna extendida; inclina el tronco desde la cadera y sostén 30 segundos.'],
            'Movilidad torácica en cuadrupedia' => [['Movilidad'], ['Espalda'], ['Peso corporal'], 'En cuatro apoyos, mano detrás de la cabeza; rota el tronco abriendo el codo al techo.'],
            'Sentadilla isométrica en pared' => [['Cuádriceps'], ['Glúteos'], ['Peso corporal'], 'Espalda contra la pared, rodillas a 90 grados; mantén el tiempo indicado.'],
            'Equilibrio unipodal' => [['Movilidad'], ['Pantorrillas', 'Glúteos'], ['Peso corporal'], 'Sostente sobre una pierna con la rodilla ligeramente flexionada.'],
        ];
    }

    public function run(): void
    {
        $groups = collect(self::GROUPS)->mapWithKeys(fn ($name) => [$name => MuscleGroup::query()->firstOrCreate(['slug' => Str::slug($name)], ['name' => $name])->id]);
        $equipment = collect(self::EQUIPMENT)->mapWithKeys(fn ($name) => [$name => Equipment::query()->firstOrCreate(['slug' => Str::slug($name)], ['name' => $name])->id]);

        foreach ($this->exercises() as $name => [$primary, $secondary, $tools, $instructions]) {
            $exercise = Exercise::withTrashed()->firstOrCreate(['slug' => Str::slug($name)], [
                'name' => $name,
                'instructions' => $instructions,
                'is_active' => true,
            ]);

            if (! $exercise->wasRecentlyCreated) {
                continue;
            }

            $exercise->muscleGroups()->sync(
                collect($primary)->mapWithKeys(fn ($g) => [$groups[$g] => ['is_primary' => true]])
                    ->union(collect($secondary)->mapWithKeys(fn ($g) => [$groups[$g] => ['is_primary' => false]]))
                    ->all()
            );
            $exercise->equipment()->sync(collect($tools)->map(fn ($t) => $equipment[$t])->all());
        }
    }
}
