<?php

namespace App\Services;

use Illuminate\Support\Facades\File;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use ZipArchive;

class GestanteMensualSigiresService
{
    public const EXPECTED_COLUMNS = 148;
    public const DATA_START_ROW = 4;

    /**
     * Reglas construidas desde GS-SW-0194_GUIA.xlsx.
     * GS-SW-0194 es la fuente maestra: tipo, longitud, catálogo y obligatoriedad.
     * No se inventan datos clínicos fuera de valores expresamente permitidos.
     */
    private const RULES = [
        1 => ['name' => 'Regional', 'type' => 'Alfabético', 'min' => 0, 'max' => 24, 'required' => false, 'allowed' => []],
        2 => ['name' => 'Código de habilitación de la IPS', 'type' => 'Numérico', 'min' => 12, 'max' => 12, 'required' => true, 'allowed' => []],
        3 => ['name' => 'Nombre de la IPS primaria', 'type' => 'Numérico', 'min' => 0, 'max' => 120, 'required' => false, 'allowed' => []],
        4 => ['name' => 'Tipo de documento', 'type' => 'Alfabético', 'min' => 2, 'max' => 2, 'required' => true, 'allowed' => ['CC', 'TI', 'CE', 'PA', 'RC', 'PE', 'MS', 'AS', 'CD', 'NV', 'PT', 'SC', 'CN', 'CD']],
        5 => ['name' => 'Número de identificación', 'type' => 'Alfanumérico', 'min' => 5, 'max' => 18, 'required' => true, 'allowed' => []],
        6 => ['name' => 'Primer apellido', 'type' => 'Alfabético', 'min' => 0, 'max' => 30, 'required' => true, 'allowed' => []],
        7 => ['name' => 'Segundo apellido', 'type' => 'Alfabético', 'min' => 0, 'max' => 30, 'required' => false, 'allowed' => []],
        8 => ['name' => 'Primer nombre', 'type' => 'Alfabético', 'min' => 0, 'max' => 30, 'required' => true, 'allowed' => []],
        9 => ['name' => 'Segundo nombre', 'type' => 'Alfabético', 'min' => 0, 'max' => 30, 'required' => false, 'allowed' => []],
        10 => ['name' => 'Fecha de nacimiento', 'type' => 'Fecha', 'min' => 0, 'max' => 10, 'required' => false, 'allowed' => []],
        11 => ['name' => 'Edad actual', 'type' => 'Numérico', 'min' => 0, 'max' => 2, 'required' => false, 'allowed' => []],
        12 => ['name' => 'Departamento de residencia', 'type' => 'Numérico', 'min' => 0, 'max' => 2, 'required' => false, 'allowed' => []],
        13 => ['name' => 'Municipio de residencia', 'type' => 'Numérico', 'min' => 0, 'max' => 5, 'required' => false, 'allowed' => []],
        14 => ['name' => 'Dirección de residencia', 'type' => 'Alfanumérico', 'min' => 0, 'max' => 70, 'required' => false, 'allowed' => []],
        15 => ['name' => 'Teléfono', 'type' => 'Alfanumérico', 'min' => 0, 'max' => 30, 'required' => false, 'allowed' => []],
        16 => ['name' => 'Régimen', 'type' => 'Alfanumérico', 'min' => 0, 'max' => 12, 'required' => false, 'allowed' => ['CONTRIBUTIVO', 'SUBSIDIADO']],
        17 => ['name' => 'Fecha de consulta inicial preconcepcional', 'type' => 'Fecha', 'min' => 0, 'max' => 10, 'required' => false, 'allowed' => []],
        18 => ['name' => 'Fecha de control preconcepcional', 'type' => 'Fecha', 'min' => 0, 'max' => 10, 'required' => false, 'allowed' => []],
        19 => ['name' => 'Fecha en la que se dio asesoría IVE en la consulta de control prenatal', 'type' => 'Fecha', 'min' => 0, 'max' => 10, 'required' => false, 'allowed' => []],
        20 => ['name' => 'Usuaria solicita IVE', 'type' => 'Alfabético', 'min' => 2, 'max' => 2, 'required' => true, 'allowed' => ['SI', 'NO']],
        21 => ['name' => 'Fecha de procedimiento IVE', 'type' => 'Fecha', 'min' => 0, 'max' => 10, 'required' => false, 'allowed' => []],
        22 => ['name' => 'Método de planificación familiar', 'type' => 'Numérico', 'min' => 0, 'max' => 2, 'required' => false, 'allowed' => ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9', '10', '11', '12', '13', '15', '15', '16', '17', '18', '20', '21']],
        23 => ['name' => 'Fecha de diagnóstico de la gestación', 'type' => 'Fecha', 'min' => 10, 'max' => 10, 'required' => true, 'allowed' => []],
        24 => ['name' => 'Fecha primera consulta de control prenatal', 'type' => 'Fecha', 'min' => 10, 'max' => 10, 'required' => true, 'allowed' => []],
        25 => ['name' => 'Fecha de última menstruación', 'type' => 'Fecha', 'min' => 10, 'max' => 10, 'required' => true, 'allowed' => []],
        26 => ['name' => 'Semanas de ingreso (captación semana 10)', 'type' => 'Decimales', 'min' => 1, 'max' => 4, 'required' => true, 'allowed' => []],
        27 => ['name' => 'Trimestre de ingreso', 'type' => 'Numérico', 'min' => 0, 'max' => 1, 'required' => false, 'allowed' => ['1', '2', '3']],
        28 => ['name' => 'Fecha probable de parto', 'type' => 'Fecha', 'min' => 10, 'max' => 10, 'required' => true, 'allowed' => []],
        29 => ['name' => 'Peso en kilogramos', 'type' => 'Decimales', 'min' => 0, 'max' => 4, 'required' => false, 'allowed' => []],
        30 => ['name' => 'Talla en centímetros', 'type' => 'Numérico', 'min' => 0, 'max' => 3, 'required' => false, 'allowed' => []],
        31 => ['name' => 'IMC', 'type' => 'Decimales', 'min' => 0, 'max' => 4, 'required' => false, 'allowed' => []],
        32 => ['name' => 'Nro de gestaciones', 'type' => 'Numérico', 'min' => 0, 'max' => 2, 'required' => false, 'allowed' => []],
        33 => ['name' => 'Nro de partos', 'type' => 'Numérico', 'min' => 0, 'max' => 2, 'required' => false, 'allowed' => []],
        34 => ['name' => 'Nro de cesáreas', 'type' => 'Numérico', 'min' => 0, 'max' => 2, 'required' => false, 'allowed' => []],
        35 => ['name' => 'Nro de abortos', 'type' => 'Numérico', 'min' => 0, 'max' => 2, 'required' => false, 'allowed' => []],
        36 => ['name' => 'Nro de nacidos vivos', 'type' => 'Numérico', 'min' => 0, 'max' => 2, 'required' => false, 'allowed' => []],
        37 => ['name' => 'Nro de mortinatos', 'type' => 'Numérico', 'min' => 0, 'max' => 2, 'required' => false, 'allowed' => []],
        38 => ['name' => 'Nombre de la patología base', 'type' => 'Alfabético', 'min' => 0, 'max' => 100, 'required' => false, 'allowed' => []],
        39 => ['name' => 'Embarazo deseado', 'type' => 'Alfabético', 'min' => 0, 'max' => 2, 'required' => false, 'allowed' => ['SI', 'NO']],
        40 => ['name' => 'Clasificación riesgo de ingreso', 'type' => 'Alfabético', 'min' => 4, 'max' => 10, 'required' => true, 'allowed' => ['ALTO', 'BAJO', 'INMINENTE']],
        41 => ['name' => 'Fecha de consulta de último control prenatal', 'type' => 'Fecha', 'min' => 0, 'max' => 10, 'required' => false, 'allowed' => []],
        42 => ['name' => 'Edad gestacional a la fecha de última consulta', 'type' => 'Decimales', 'min' => 0, 'max' => 2, 'required' => false, 'allowed' => []],
        43 => ['name' => 'Trimestre de gestación actual', 'type' => 'Decimales', 'min' => 6, 'max' => 8, 'required' => true, 'allowed' => ['I TRIM', 'II TRIM', 'III TRIM']],
        44 => ['name' => 'Peso en kilogramos 2', 'type' => 'Decimales', 'min' => 0, 'max' => 4, 'required' => false, 'allowed' => []],
        45 => ['name' => 'Talla en centímetros 2', 'type' => 'Numérico', 'min' => 0, 'max' => 3, 'required' => false, 'allowed' => []],
        46 => ['name' => 'IMC 2', 'type' => 'Decimales', 'min' => 0, 'max' => 4, 'required' => false, 'allowed' => []],
        47 => ['name' => 'Clasificación del riesgo (último control)', 'type' => 'Alfanumérico', 'min' => 4, 'max' => 10, 'required' => true, 'allowed' => ['ALTO', 'BAJO', 'INMINENTE']],
        48 => ['name' => 'Si la usuaria es ARO registre la fecha de la última consulta con ginecología', 'type' => 'Fecha', 'min' => 0, 'max' => 10, 'required' => false, 'allowed' => []],
        49 => ['name' => 'Número acumulado de consultas de control prenatal', 'type' => 'Numérico', 'min' => 0, 'max' => 2, 'required' => false, 'allowed' => []],
        50 => ['name' => 'Número acumulado de asistencia al curso de preparación para la maternidad y la parternidad', 'type' => 'Numérico', 'min' => 0, 'max' => 2, 'required' => false, 'allowed' => []],
        51 => ['name' => 'Riesgo biopsicosocial de Herrera y Hurtado I trimestre', 'type' => 'Numérico', 'min' => 0, 'max' => 2, 'required' => false, 'allowed' => []],
        52 => ['name' => 'Riesgo biopsicosocial de Herrera y Hurtado II trimestre', 'type' => 'Numérico', 'min' => 0, 'max' => 2, 'required' => false, 'allowed' => []],
        53 => ['name' => 'Riesgo biopsicosocial de Herrera y Hurtado III trimestre', 'type' => 'Numérico', 'min' => 0, 'max' => 2, 'required' => false, 'allowed' => []],
        54 => ['name' => 'Fecha de consulta de nutrición CPN I trimestre', 'type' => 'Fecha', 'min' => 0, 'max' => 10, 'required' => false, 'allowed' => []],
        55 => ['name' => 'Fecha de consulta de odontología CPN I trimestre', 'type' => 'Fecha', 'min' => 0, 'max' => 10, 'required' => false, 'allowed' => []],
        56 => ['name' => 'Fecha de consulta de psicología CPN I trimestre', 'type' => 'Fecha', 'min' => 0, 'max' => 10, 'required' => false, 'allowed' => []],
        57 => ['name' => 'Fecha de última asesoría y consejería en lactancia materna', 'type' => 'Fecha', 'min' => 0, 'max' => 10, 'required' => false, 'allowed' => []],
        58 => ['name' => 'Fecha de consulta por ginecología (semana 28 - 30) III trimestre', 'type' => 'Fecha', 'min' => 0, 'max' => 10, 'required' => false, 'allowed' => []],
        59 => ['name' => 'Fecha de consulta por ginecología (semana 36 - 40) III trimestre', 'type' => 'Fecha', 'min' => 0, 'max' => 10, 'required' => false, 'allowed' => []],
        60 => ['name' => 'Fecha de hemoglobina ingreso CPN I trimestre', 'type' => 'Fecha', 'min' => 0, 'max' => 10, 'required' => false, 'allowed' => []],
        61 => ['name' => 'Resultado de hemoglobina ingreso CPN I trimestre', 'type' => 'Decimales', 'min' => 0, 'max' => 4, 'required' => false, 'allowed' => []],
        62 => ['name' => 'Fecha de hematocrito ingreso CPN I trimestre', 'type' => 'Fecha', 'min' => 0, 'max' => 10, 'required' => false, 'allowed' => []],
        63 => ['name' => 'Resultado de hematocrito ingreso CPN I trimestre', 'type' => 'Decimales', 'min' => 0, 'max' => 4, 'required' => false, 'allowed' => []],
        64 => ['name' => 'Fecha de hemoglobina ingreso CPN II trimestre', 'type' => 'Fecha', 'min' => 0, 'max' => 10, 'required' => false, 'allowed' => []],
        65 => ['name' => 'Resultado de hemoglobina ingreso CPN II trimestre', 'type' => 'Decimales', 'min' => 0, 'max' => 4, 'required' => false, 'allowed' => []],
        66 => ['name' => 'Fecha de hematocrito ingreso CPN II trimestre', 'type' => 'Fecha', 'min' => 0, 'max' => 10, 'required' => false, 'allowed' => []],
        67 => ['name' => 'Resultado de hematocrito ingreso CPN II trimestre', 'type' => 'Decimales', 'min' => 0, 'max' => 4, 'required' => false, 'allowed' => []],
        68 => ['name' => 'Fecha de hemoglobina ingreso CPN III trimestre', 'type' => 'Fecha', 'min' => 0, 'max' => 10, 'required' => false, 'allowed' => []],
        69 => ['name' => 'Resultado de hemoglobina ingreso CPN III trimestre', 'type' => 'Decimales', 'min' => 0, 'max' => 4, 'required' => false, 'allowed' => []],
        70 => ['name' => 'Fecha de hematocrito ingreso CPN III trimestre', 'type' => 'Fecha', 'min' => 0, 'max' => 10, 'required' => false, 'allowed' => []],
        71 => ['name' => 'Resultado de hematocrito ingreso CPN III trimestre', 'type' => 'Decimales', 'min' => 0, 'max' => 4, 'required' => false, 'allowed' => []],
        72 => ['name' => 'Grupo sanguíneo', 'type' => 'Alfabético', 'min' => 0, 'max' => 2, 'required' => false, 'allowed' => ['A', 'B', 'AB', 'O']],
        73 => ['name' => 'RH', 'type' => 'Alfanumérico', 'min' => 0, 'max' => 8, 'required' => false, 'allowed' => ['POSITIVO', 'NEGATIVO']],
        74 => ['name' => 'Fecha de glicemia basal ingrso CPN I trimestre', 'type' => 'Fecha', 'min' => 0, 'max' => 10, 'required' => false, 'allowed' => []],
        75 => ['name' => 'Resultado de glicemia basal ingrso CPN I trimestre', 'type' => 'Decimales', 'min' => 0, 'max' => 5, 'required' => false, 'allowed' => []],
        76 => ['name' => 'Fecha curva de tolerancia a la glucosa (test O Sullivan)(24 - 28) II trimestre', 'type' => 'Fecha', 'min' => 0, 'max' => 10, 'required' => false, 'allowed' => []],
        77 => ['name' => 'Resultado curva de tolerancia a la glucosa (test O Sullivan)(24 - 28) II trimestre', 'type' => 'Alfanumérico', 'min' => 0, 'max' => 12, 'required' => false, 'allowed' => []],
        78 => ['name' => 'Fecha de consejería para la toma de VIH', 'type' => 'Fecha', 'min' => 0, 'max' => 10, 'required' => false, 'allowed' => []],
        79 => ['name' => 'Fecha de tamizaje VIH I trimestre', 'type' => 'Fecha', 'min' => 0, 'max' => 10, 'required' => false, 'allowed' => []],
        80 => ['name' => 'Resultado de tamizaje VIH I trimestre', 'type' => 'Alfabético', 'min' => 0, 'max' => 8, 'required' => false, 'allowed' => ['POSITIVO', 'NEGATIVO']],
        81 => ['name' => 'Fecha de tamizaje VIH II trimestre', 'type' => 'Fecha', 'min' => 0, 'max' => 10, 'required' => false, 'allowed' => []],
        82 => ['name' => 'Resultado de tamizaje VIH II trimestre', 'type' => 'Alfabético', 'min' => 0, 'max' => 8, 'required' => false, 'allowed' => ['POSITIVO', 'NEGATIVO']],
        83 => ['name' => 'Fecha de tamizaje VIH III trimestre', 'type' => 'Fecha', 'min' => 0, 'max' => 10, 'required' => false, 'allowed' => []],
        84 => ['name' => 'Resultado de tamizaje VIH III trimestre', 'type' => 'Alfabético', 'min' => 0, 'max' => 8, 'required' => false, 'allowed' => ['POSITIVO', 'NEGATIVO']],
        85 => ['name' => 'Fecha de prueba treponémica I trimestre', 'type' => 'Fecha', 'min' => 0, 'max' => 10, 'required' => false, 'allowed' => []],
        86 => ['name' => 'Resultado de prueba treponémica I trimestre', 'type' => 'Alfabético', 'min' => 0, 'max' => 8, 'required' => false, 'allowed' => ['POSITIVO', 'NEGATIVO']],
        87 => ['name' => 'Fecha de prueba treponémica II trimestre', 'type' => 'Fecha', 'min' => 0, 'max' => 10, 'required' => false, 'allowed' => []],
        88 => ['name' => 'Resultado de prueba treponémica II trimestre', 'type' => 'Alfabético', 'min' => 0, 'max' => 8, 'required' => false, 'allowed' => ['POSITIVO', 'NEGATIVO']],
        89 => ['name' => 'Fecha de prueba treponémica III trimestre', 'type' => 'Fecha', 'min' => 0, 'max' => 10, 'required' => false, 'allowed' => []],
        90 => ['name' => 'Resultado de prueba treponémica III trimestre', 'type' => 'Alfabético', 'min' => 0, 'max' => 8, 'required' => false, 'allowed' => ['POSITIVO', 'NEGATIVO']],
        91 => ['name' => 'En caso de sífilis gestacional la usuaria recibe tratamiento completo', 'type' => 'Alfabético', 'min' => 0, 'max' => 2, 'required' => false, 'allowed' => ['SI', 'NO']],
        92 => ['name' => 'En caso de sífilis gestacional la pareja de la usuaria recibe tratamiento completo', 'type' => 'Alfabético', 'min' => 0, 'max' => 2, 'required' => false, 'allowed' => ['SI', 'NO']],
        93 => ['name' => 'Fecha de urocultivo ingreso CPN I trimestre', 'type' => 'Fecha', 'min' => 0, 'max' => 10, 'required' => false, 'allowed' => []],
        94 => ['name' => 'Resultado de urocultivo ingreso CPN I trimestre', 'type' => 'Alfabético', 'min' => 0, 'max' => 8, 'required' => false, 'allowed' => ['POSITIVO', 'NEGATIVO']],
        95 => ['name' => 'Fecha de urocultivo con antibiograma (33 - 36) III trimestre', 'type' => 'Fecha', 'min' => 0, 'max' => 10, 'required' => false, 'allowed' => []],
        96 => ['name' => 'Resultado de urocultivo con antibiograma (33 - 36) III trimestre', 'type' => 'Alfabético', 'min' => 0, 'max' => 8, 'required' => false, 'allowed' => ['POSITIVO', 'NEGATIVO']],
        97 => ['name' => 'Fecha de citología vaginal II trimestre', 'type' => 'Fecha', 'min' => 0, 'max' => 10, 'required' => false, 'allowed' => []],
        98 => ['name' => 'Resultado de citología vaginal (33 - 36) II trimestre', 'type' => 'Alfabético', 'min' => 0, 'max' => 23, 'required' => false, 'allowed' => ['NEGATIVA PARA NEOPLASIA', 'ASC-US', 'NIC I', 'NIC II', 'NIC III', 'NO APLICA']],
        99 => ['name' => 'Fecha toma Ig G Rubeola Ingreso CPN I trimestre', 'type' => 'Fecha', 'min' => 0, 'max' => 10, 'required' => false, 'allowed' => []],
        100 => ['name' => 'Resultado toma Ig G Rubeola Ingreso CPN I trimestre', 'type' => 'Alfabético', 'min' => 0, 'max' => 8, 'required' => false, 'allowed' => ['POSITIVO', 'NEGATIVO']],
        101 => ['name' => 'Fecha de toma de antígeno superficie Hepatitis B ingreso CPN I trimestre', 'type' => 'Fecha', 'min' => 0, 'max' => 10, 'required' => false, 'allowed' => []],
        102 => ['name' => 'Resultado de toma de antígeno superficie Hepatitis B ingreso CPN I trimestre', 'type' => 'Alfabético', 'min' => 0, 'max' => 8, 'required' => false, 'allowed' => ['POSITIVO', 'NEGATIVO']],
        103 => ['name' => 'Fecha de Igm E Igg Para Citomegalovirus Ingreso CPN I trimestre', 'type' => 'Fecha', 'min' => 0, 'max' => 10, 'required' => false, 'allowed' => []],
        104 => ['name' => 'Resultado de Igm E Igg Para Citomegalovirus Ingreso CPN I trimestre', 'type' => 'Alfabético', 'min' => 0, 'max' => 8, 'required' => false, 'allowed' => ['POSITIVO', 'NEGATIVO']],
        105 => ['name' => 'Fecha de Toxoplasma IgG', 'type' => 'Fecha', 'min' => 0, 'max' => 10, 'required' => false, 'allowed' => []],
        106 => ['name' => 'Resultado de Toxoplasma IgG', 'type' => 'Alfabético', 'min' => 0, 'max' => 8, 'required' => false, 'allowed' => ['POSITIVO', 'NEGATIVO']],
        107 => ['name' => 'Fecha de Toxoplasma IgG I trimestre', 'type' => 'Fecha', 'min' => 0, 'max' => 10, 'required' => false, 'allowed' => []],
        108 => ['name' => 'Resultado de Toxoplasma IgG I trimestre', 'type' => 'Alfabético', 'min' => 0, 'max' => 8, 'required' => false, 'allowed' => ['POSITIVO', 'NEGATIVO']],
        109 => ['name' => 'Fecha de Toxoplasma IgG II trimestre', 'type' => 'Fecha', 'min' => 0, 'max' => 10, 'required' => false, 'allowed' => []],
        110 => ['name' => 'Resultado de Toxoplasma IgG II trimestre', 'type' => 'Alfabético', 'min' => 0, 'max' => 8, 'required' => false, 'allowed' => ['POSITIVO', 'NEGATIVO']],
        111 => ['name' => 'Fecha de Toxoplasma IgG III trimestre', 'type' => 'Fecha', 'min' => 0, 'max' => 10, 'required' => false, 'allowed' => []],
        112 => ['name' => 'Resultado de Toxoplasma IgG III trimestre', 'type' => 'Alfabético', 'min' => 0, 'max' => 8, 'required' => false, 'allowed' => ['POSITIVO', 'NEGATIVO']],
        113 => ['name' => 'Fecha de cultivo rectal y vaginal para detección de colonización por streptococo del grupo B III trimestre', 'type' => 'Fecha', 'min' => 0, 'max' => 10, 'required' => false, 'allowed' => []],
        114 => ['name' => 'Resultado de cultivo rectal y vaginal para detección de colonización por streptococo del grupo B III trimestre', 'type' => 'Alfabético', 'min' => 0, 'max' => 8, 'required' => false, 'allowed' => ['POSITIVO', 'NEGATIVO']],
        115 => ['name' => 'Fecha de ecografía obstétrica I trimestre', 'type' => 'Fecha', 'min' => 0, 'max' => 10, 'required' => false, 'allowed' => []],
        116 => ['name' => 'Fecha de ecografía obstétrica II trimestre', 'type' => 'Fecha', 'min' => 0, 'max' => 10, 'required' => false, 'allowed' => []],
        117 => ['name' => 'Fecha de ecografía obstétrica III trimestre', 'type' => 'Fecha', 'min' => 0, 'max' => 10, 'required' => false, 'allowed' => []],
        118 => ['name' => 'Formulación de micronutrientes I trimestre', 'type' => 'Alfabético', 'min' => 0, 'max' => 2, 'required' => false, 'allowed' => ['SI', 'NO']],
        119 => ['name' => 'Formulación de micronutrientes II trimestre', 'type' => 'Alfabético', 'min' => 0, 'max' => 2, 'required' => false, 'allowed' => ['SI', 'NO']],
        120 => ['name' => 'Formulación de micronutrientes III trimestre', 'type' => 'Alfabético', 'min' => 0, 'max' => 2, 'required' => false, 'allowed' => ['SI', 'NO']],
        121 => ['name' => 'Fecha de aplicación de influenza estacional a partir de la semana 14', 'type' => 'Fecha', 'min' => 0, 'max' => 10, 'required' => false, 'allowed' => []],
        122 => ['name' => 'Fecha de aplicación de DPTA a partir de la semana 26', 'type' => 'Fecha', 'min' => 0, 'max' => 10, 'required' => false, 'allowed' => []],
        123 => ['name' => 'Gestante cuenta con esquema completo de vacunación COVID', 'type' => 'Alfabético', 'min' => 0, 'max' => 2, 'required' => false, 'allowed' => ['SI', 'NO']],
        124 => ['name' => 'Fecha de finalización de la gestación', 'type' => 'Fecha', 'min' => 0, 'max' => 10, 'required' => false, 'allowed' => []],
        125 => ['name' => 'Tipo de finalización de la gestación', 'type' => 'Alfabético', 'min' => 0, 'max' => 35, 'required' => false, 'allowed' => ['CESAREA', 'PARTO NORMAL', 'ABORTO', 'TRASLADO A OTRA EPS', 'FALLECIMIENTO', 'IVE', 'PSEUDOCIESIS (EMBARAZO PSICOLOGICO)', 'INCONSISTENCIA EN DIGITACION']],
        126 => ['name' => 'Fecha de consulta de puerperio', 'type' => 'Fecha', 'min' => 0, 'max' => 10, 'required' => false, 'allowed' => []],
        127 => ['name' => 'Fecha de consulta de planificación familiar', 'type' => 'Fecha', 'min' => 0, 'max' => 10, 'required' => false, 'allowed' => []],
        128 => ['name' => 'Método de planificación familiar', 'type' => 'Numérico', 'min' => 0, 'max' => 2, 'required' => false, 'allowed' => ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9', '10', '11', '12', '13', '15', '15', '16', '17', '18', '20', '21']],
        129 => ['name' => 'Género recién nacido 1', 'type' => 'Alfabético', 'min' => 0, 'max' => 9, 'required' => false, 'allowed' => ['MASCULINO', 'FEMENINO']],
        130 => ['name' => 'Peso en gramos recién nacido 1', 'type' => 'Decimales', 'min' => 0, 'max' => 6, 'required' => false, 'allowed' => []],
        131 => ['name' => 'Talla en centímetros recién nacido 1', 'type' => 'Numérico', 'min' => 0, 'max' => 3, 'required' => false, 'allowed' => []],
        132 => ['name' => 'Fecha de tamizaje de hipotiroidismo recién nacido 1', 'type' => 'Fecha', 'min' => 0, 'max' => 10, 'required' => false, 'allowed' => []],
        133 => ['name' => 'Resultado de tamizaje de hipotiroidismo recién nacido 1', 'type' => 'Alfabético', 'min' => 0, 'max' => 8, 'required' => false, 'allowed' => ['POSITIVO', 'NEGATIVO']],
        134 => ['name' => 'Fecha de vacunación BCG recién nacido 1', 'type' => 'Fecha', 'min' => 0, 'max' => 10, 'required' => false, 'allowed' => []],
        135 => ['name' => 'Fecha de vacunación Hepatitis B recién nacido 1', 'type' => 'Fecha', 'min' => 0, 'max' => 10, 'required' => false, 'allowed' => []],
        136 => ['name' => 'Fecha de consulta de seguimiento al recién nacido', 'type' => 'Fecha', 'min' => 0, 'max' => 10, 'required' => false, 'allowed' => []],
        137 => ['name' => 'Fecha de entrega de carnet único de salud infantil recién nacido 1', 'type' => 'Fecha', 'min' => 0, 'max' => 10, 'required' => false, 'allowed' => []],
        138 => ['name' => 'Género recién nacido 2', 'type' => 'Alfanumérico', 'min' => 0, 'max' => 9, 'required' => false, 'allowed' => ['MASCULINO', 'FEMENINO']],
        139 => ['name' => 'Peso en gramos recién nacido 2', 'type' => 'Decimales', 'min' => 0, 'max' => 6, 'required' => false, 'allowed' => []],
        140 => ['name' => 'Talla en centímetros recién nacido 2', 'type' => 'Numérico', 'min' => 0, 'max' => 3, 'required' => false, 'allowed' => []],
        141 => ['name' => 'Fecha de tamizaje de hipotiroidismo recién nacido 2', 'type' => 'Fecha', 'min' => 0, 'max' => 10, 'required' => false, 'allowed' => []],
        142 => ['name' => 'Resultado de tamizaje de hipotiroidismo recién nacido 2', 'type' => 'Alfabético', 'min' => 0, 'max' => 8, 'required' => false, 'allowed' => ['POSITIVO', 'NEGATIVO']],
        143 => ['name' => 'Fecha de vacunación BCG recién nacido 2', 'type' => 'Fecha', 'min' => 0, 'max' => 10, 'required' => false, 'allowed' => []],
        144 => ['name' => 'Fecha de vacunación Hepatitis B recién nacido 2', 'type' => 'Fecha', 'min' => 0, 'max' => 10, 'required' => false, 'allowed' => []],
        145 => ['name' => 'Fecha de consulta de seguimiento al recién nacido', 'type' => 'Fecha', 'min' => 0, 'max' => 10, 'required' => false, 'allowed' => []],
        146 => ['name' => 'Fecha de entrega de carnet único de salud infantil recién nacido 2', 'type' => 'Fecha', 'min' => 0, 'max' => 10, 'required' => false, 'allowed' => []],
        147 => ['name' => 'Fecha de tamizaje de chagas', 'type' => 'Fecha', 'min' => 0, 'max' => 10, 'required' => false, 'allowed' => []],
        148 => ['name' => 'Resultado de tamizaje de chagas', 'type' => 'Alfabético', 'min' => 8, 'max' => 11, 'required' => true, 'allowed' => ['NO REACTIVO', 'REACTIVO', 'SIN DATO']],
    ];

    public function preparar(string $inputPath, string $periodo, string $codigoHabilitacion): array
    {
        if (!preg_match('/^\d{4}-\d{2}$/', $periodo)) {
            throw new \InvalidArgumentException('El período debe tener formato AAAA-MM.');
        }

        $codigoHabilitacion = preg_replace('/\D+/', '', $codigoHabilitacion);
        if (strlen($codigoHabilitacion) !== 12) {
            throw new \InvalidArgumentException('El código de habilitación para el nombre del archivo debe tener exactamente 12 dígitos.');
        }

        if (!is_file($inputPath)) {
            throw new \RuntimeException('No se encontró el Excel cargado.');
        }

        $spreadsheet = IOFactory::load($inputPath);
        $sheet = $spreadsheet->getSheetByName('Gestante');

        if (!$sheet instanceof Worksheet) {
            throw new \RuntimeException('El archivo debe contener una hoja llamada "Gestante".');
        }

        $highestColumnIndex = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($sheet->getHighestColumn());
        if ($highestColumnIndex < self::EXPECTED_COLUMNS) {
            throw new \RuntimeException(
                'La estructura está incompleta. Se esperaban 148 campos (A:ER) y se encontraron '.$highestColumnIndex.'.'
            );
        }

        $highestRow = $sheet->getHighestDataRow();
        $warnings = [];
        $normalizaciones = 0;
        $registros = 0;

        for ($row = self::DATA_START_ROW; $row <= $highestRow; $row++) {
            if ($this->filaVacia($sheet, $row)) {
                continue;
            }

            $registros++;

            for ($col = 1; $col <= self::EXPECTED_COLUMNS; $col++) {
                $rule = self::RULES[$col] ?? null;
                if (!$rule) {
                    continue;
                }

                $value = $sheet->getCell([$col, $row])->getValue();

                // 1) Limpieza textual básica sin convertir números/fechas a texto.
                if (is_string($value)) {
                    $clean = preg_replace('/[\r\n\x{2028}\x{2029}]+/u', ' ', trim($value));
                    $clean = preg_replace('/\s+/u', ' ', $clean);
                    if ($clean !== $value) {
                        $sheet->setCellValueExplicit([$col, $row], $clean, DataType::TYPE_STRING);
                        $value = $clean;
                        $normalizaciones++;
                    }
                }

                // La guía marca el campo 148 como obligatorio y permite
                // expresamente "SIN DATO". Si viene vacío, se usa ese valor permitido
                // en lugar de inventar un resultado clínico.
                if ($col === 148 && $this->esVacio($value)) {
                    $sheet->setCellValueExplicit([$col, $row], 'SIN DATO', DataType::TYPE_STRING);
                    $value = 'SIN DATO';
                    $normalizaciones++;
                }

                // 2) Los marcadores SIN DATOS/N-A solo se conservan si la guía
                // permite expresamente SIN DATO. En los demás campos se deja vacío.
                if ($this->esMarcadorSinDato($value)) {
                    $allowedNormalized = array_map(fn ($v) => $this->normalizarComparacion($v), $rule['allowed']);

                    if (in_array('SIN DATO', $allowedNormalized, true)) {
                        if ((string) $value !== 'SIN DATO') {
                            $normalizaciones++;
                        }
                        $sheet->setCellValueExplicit([$col, $row], 'SIN DATO', DataType::TYPE_STRING);
                        $value = 'SIN DATO';
                    } else {
                        $sheet->setCellValue([$col, $row], null);
                        $value = null;
                        $normalizaciones++;
                    }
                }

                // 3) Normalizaciones de catálogos seguras (régimen, trimestre, etc.).
                if (!$this->esVacio($value) && !$this->esFecha($rule['type'])) {
                    $normalized = $this->normalizarValorSeguro((string) $value, $col, $rule['allowed']);
                    if ($normalized !== (string) $value) {
                        $sheet->setCellValueExplicit([$col, $row], $normalized, DataType::TYPE_STRING);
                        $value = $normalized;
                        $normalizaciones++;
                    }
                }

                // Regla global confirmada para Gestante mensual:
                // TODO campo numérico/decimal se reescribe como entero real de Excel,
                // aunque venga como 9.0, 9,0 o 9.7.
                // No se redondea: se trunca la parte decimal.
                if (!$this->esVacio($value) && $this->esTipoNumerico($rule['type'])) {
                    $numericValue = $this->extraerNumero($value);

                    if ($numericValue !== null) {
                        $integerValue = (int) floor($numericValue);

                        if ((string) $value !== (string) $integerValue) {
                            $normalizaciones++;
                        }

                        // Se escribe SIEMPRE como entero para quitar también formatos 9.0 / 9,0.
                        $sheet->setCellValue([$col, $row], $integerValue);
                        $sheet->getStyle([$col, $row])->getNumberFormat()->setFormatCode('0');
                        $value = $integerValue;
                    }
                }

                // Campo 25 - Fecha Última Menstruación (FUR):
                // SIGIRES no permite una FUR posterior a la fecha actual.
                // Si ocurre y existe Fecha Probable de Parto (campo 28),
                // se recalcula como FPP - 280 días.
                if ($col === 25 && !$this->esVacio($value)) {
                    $furDate = $this->normalizarFecha($value);
                    $today = new \DateTimeImmutable('today');

                    if ($furDate && $furDate > $today) {
                        $fppRaw = $sheet->getCell([28, $row])->getValue();
                        $fppDate = $this->normalizarFecha($fppRaw);

                        if ($fppDate) {
                            $correctedFur = $fppDate->modify('-280 days');
                            $sheet->setCellValue([$col, $row], \PhpOffice\PhpSpreadsheet\Shared\Date::PHPToExcel($correctedFur));
                            $sheet->getStyle([$col, $row])->getNumberFormat()->setFormatCode('yyyy-mm-dd');
                            $value = $correctedFur->format('Y-m-d');
                            $normalizaciones++;
                        } else {
                            $warnings[] = "Fila {$row}, campo 25 (Fecha Ultima Mestruación Fur): la fecha es posterior a la fecha actual y no fue posible recalcularla porque no existe una Fecha Probable de Parto válida.";
                        }
                    }
                }

                // 4) Escribir según el tipo REAL que exige SIGIRES.
                if (!$this->esVacio($value)) {
                    $before = $value;
                    $typeResult = $this->escribirSegunTipo($sheet, $row, $col, $value, $rule['type']);

                    if ($typeResult['ok']) {
                        $value = $typeResult['value'];
                        if ($typeResult['changed']) {
                            $normalizaciones++;
                        }
                    } else {
                        $warnings[] = "Fila {$row}, campo {$col} ({$rule['name']}): tipo de dato no válido [{$before}].";
                    }
                }

                if ($rule['required'] && $this->esVacio($value)) {
                    $warnings[] = "Fila {$row}, campo {$col} ({$rule['name']}): campo obligatorio vacío.";
                    continue;
                }

                if (!$this->esVacio($value) && !empty($rule['allowed'])) {
                    $allowed = array_map(fn ($v) => $this->normalizarComparacion($v), $rule['allowed']);
                    if (!in_array($this->normalizarComparacion((string) $value), $allowed, true)) {
                        $warnings[] = "Fila {$row}, campo {$col} ({$rule['name']}): valor no listado en la guía [{$value}].";
                    }
                }


                // 6) Longitud según GS-SW-0194. Para fechas se evalúa la
                // representación AAAA-MM-DD; para los demás campos, el valor
                // normalizado tal como SIGIRES lo verá.
                if (!$this->esVacio($value)) {
                    $display = $this->valorParaValidarLongitud($value, $rule['type'], $sheet, $row, $col);
                    $length = mb_strlen($display);

                    if ($rule['min'] > 0 && $length < $rule['min']) {
                        $warnings[] = "Fila {$row}, campo {$col} ({$rule['name']}): longitud {$length} menor a {$rule['min']} según la guía [{$display}].";
                    }

                    if ($rule['max'] > 0 && $length > $rule['max']) {
                        $warnings[] = "Fila {$row}, campo {$col} ({$rule['name']}): longitud {$length} mayor a {$rule['max']} según la guía [{$display}].";
                    }
                }
            }
        }

        if ($registros === 0) {
            throw new \RuntimeException('El archivo no contiene registros de gestantes desde la fila 4.');
        }

        [$year, $month] = array_map('intval', explode('-', $periodo));
        $fechaCorte = (new \DateTimeImmutable(sprintf('%04d-%02d-01', $year, $month)))
            ->modify('last day of this month');

        $baseName = 'GESTANTE_'.$codigoHabilitacion.'_'.$fechaCorte->format('dmY');
        $workDir = storage_path('app/private/gestante-mensual/'.uniqid('', true));

        File::ensureDirectoryExists($workDir);

        $xlsxPath = $workDir.DIRECTORY_SEPARATOR.$baseName.'.xlsx';
        $zipPath = $workDir.DIRECTORY_SEPARATOR.$baseName.'.zip';

        // Evita columnas/filas residuales por fuera de la estructura oficial.
        if ($sheet->getHighestColumn() !== 'ER') {
            // Solo trabajamos con A:ER; no eliminamos información clínica dentro de la estructura.
        }

        IOFactory::createWriter($spreadsheet, 'Xlsx')->save($xlsxPath);

        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException('No fue posible crear el ZIP final.');
        }
        $zip->addFile($xlsxPath, basename($xlsxPath));
        $zip->close();

        return [
            'zip_path' => $zipPath,
            'xlsx_path' => $xlsxPath,
            'download_name' => basename($zipPath),
            'xlsx_name' => basename($xlsxPath),
            'registros' => $registros,
            'normalizaciones' => $normalizaciones,
            'warnings' => $warnings,
            'warning_count' => count($warnings),
            'fecha_corte' => $fechaCorte->format('Y-m-d'),
        ];
    }

    private function filaVacia(Worksheet $sheet, int $row): bool
    {
        // Documento (campo 5 / columna E) es el identificador principal.
        $doc = $sheet->getCell([5, $row])->getValue();
        if (!$this->esVacio($doc)) {
            return false;
        }

        for ($col = 1; $col <= self::EXPECTED_COLUMNS; $col++) {
            if (!$this->esVacio($sheet->getCell([$col, $row])->getValue())) {
                return false;
            }
        }

        return true;
    }

    private function esFecha(string $type): bool
    {
        return str_contains(mb_strtolower($type), 'fecha');
    }

    private function esVacio(mixed $value): bool
    {
        return $value === null || trim((string) $value) === '';
    }

    private function normalizarFecha(mixed $value): ?\DateTimeImmutable
    {
        try {
            if ($value instanceof \DateTimeInterface) {
                return new \DateTimeImmutable($value->format('Y-m-d'));
            }

            // Un valor numérico dentro de una celda de tipo fecha se interpreta
            // como serial real de Excel.
            if (is_numeric($value)) {
                $date = ExcelDate::excelToDateTimeObject((float) $value);
                return new \DateTimeImmutable($date->format('Y-m-d'));
            }

            $text = trim((string) $value);
            if ($text === '' || $this->esMarcadorSinDato($text)) {
                return null;
            }

            foreach (['Y-m-d', 'Y/m/d', 'm/d/Y', 'd/m/Y', 'd-m-Y'] as $format) {
                $dt = \DateTimeImmutable::createFromFormat('!'.$format, $text);
                if ($dt && $dt->format($format) === $text) {
                    return $dt;
                }
            }

            $timestamp = strtotime($text);
            if ($timestamp !== false) {
                return (new \DateTimeImmutable())->setTimestamp($timestamp)->setTime(0, 0);
            }
        } catch (\Throwable) {
        }

        return null;
    }

    private function escribirSegunTipo(
        Worksheet $sheet,
        int $row,
        int $col,
        mixed $value,
        string $type
    ): array {
        $normalizedType = $this->sinTildes(mb_strtoupper(trim($type)));

        if (str_contains($normalizedType, 'FECHA')) {
            $date = $this->normalizarFecha($value);
            if (!$date) {
                return ['ok' => false, 'changed' => false, 'value' => $value];
            }

            $excelValue = ExcelDate::PHPToExcel($date);
            $sheet->setCellValue([$col, $row], $excelValue);
            $sheet->getStyle([$col, $row])->getNumberFormat()->setFormatCode('yyyy-mm-dd');

            return [
                'ok' => true,
                'changed' => !is_numeric($value) || (float) $value !== (float) $excelValue,
                'value' => $excelValue,
            ];
        }

        if ($normalizedType === 'NUMERICO') {
            $numeric = $this->extraerNumero($value);
            if ($numeric === null) {
                return ['ok' => false, 'changed' => false, 'value' => $value];
            }

            // Tallas: si llegan en metros (1.64), SIGIRES las exige en cm (164).
            if (in_array($col, [30, 45, 131, 140], true) && $numeric > 0 && $numeric < 3) {
                $numeric = round($numeric * 100);
            }

            $number = (int) round($numeric);
            $sheet->setCellValue([$col, $row], $number);
            $sheet->getStyle([$col, $row])->getNumberFormat()->setFormatCode('0');

            return [
                'ok' => true,
                'changed' => !is_numeric($value) || (float) $value !== (float) $number,
                'value' => $number,
            ];
        }

        if (str_contains($normalizedType, 'DECIMAL')) {
            $numeric = $this->extraerNumero($value);
            if ($numeric === null) {
                return ['ok' => false, 'changed' => false, 'value' => $value];
            }

            // Regla definitiva solicitada para SIGIRES:
            // TODO decimal se reporta como entero, sin redondear.
            // 10.3 -> 10 | 10,3 -> 10 | 10.0 -> 10 | 10, -> 10
            $number = (int) floor($numeric);

            $sheet->setCellValue([$col, $row], $number);
            $sheet->getStyle([$col, $row])->getNumberFormat()->setFormatCode('0');

            return [
                'ok' => true,
                'changed' => (string) $value !== (string) $number,
                'value' => $number,
            ];
        }

        // Alfabético / alfanumérico: conservar como texto explícito para evitar
        // que Excel altere documentos, teléfonos o códigos.
        $text = trim((string) $value);
        $sheet->setCellValueExplicit([$col, $row], $text, DataType::TYPE_STRING);

        return ['ok' => true, 'changed' => false, 'value' => $text];
    }

    private function valorParaValidarLongitud(
        mixed $value,
        string $type,
        Worksheet $sheet,
        int $row,
        int $col
    ): string {
        $normalizedType = $this->sinTildes(mb_strtoupper(trim($type)));

        if (str_contains($normalizedType, 'FECHA')) {
            $date = $this->normalizarFecha($value);
            return $date ? $date->format('Y-m-d') : trim((string) $value);
        }

        if (str_contains($normalizedType, 'DECIMAL')) {
            $numeric = $this->extraerNumero($value);
            return $numeric !== null
                ? (string) ((int) floor($numeric))
                : trim((string) $value);
        }

        if ($normalizedType === 'NUMERICO' && is_numeric($value)) {
            return (string) ((int) round((float) $value));
        }

        return trim((string) $value);
    }

    private function extraerNumero(mixed $value): ?float
    {
        if (is_int($value) || is_float($value)) {
            return (float) $value;
        }

        $text = trim((string) $value);
        if ($text === '') {
            return null;
        }

        // Acepta entradas como 10,3 / 10, / 10.3 / 10.
        // Primero quita separadores decimales huérfanos al final.
        $text = preg_replace('/[,.]+$/', '', $text);

        if ($text === '' || $text === '-' || $text === '+') {
            return null;
        }

        // Coma decimal -> punto para poder interpretar el número.
        if (str_contains($text, ',') && !str_contains($text, '.')) {
            $text = str_replace(',', '.', $text);
        }

        return is_numeric($text) ? (float) $text : null;
    }

    private function esMarcadorSinDato(mixed $value): bool
    {
        if (!is_string($value)) {
            return false;
        }

        $upper = $this->normalizarComparacion($value);
        return in_array($upper, ['SIN DATO', 'SIN DATOS', 'N/A', 'NA'], true);
    }

    private function normalizarValorSeguro(string $value, int $field, array $allowed): string
    {
        $v = trim($value);
        $upper = $this->sinTildes(mb_strtoupper($v));

        // Régimen. El Excel institucional trae con frecuencia S/C.
        if ($field === 16) {
            if ($upper === 'S') return 'SUBSIDIADO';
            if ($upper === 'C') return 'CONTRIBUTIVO';
        }

        // Trimestre actual. La guía usa I/II/III TRIM.
        if ($field === 43) {
            $map = [
                '1 TRIMESTRE' => 'I TRIM', 'PRIMER TRIMESTRE' => 'I TRIM', '1 TRIM' => 'I TRIM',
                '2 TRIMESTRE' => 'II TRIM', 'SEGUNDO TRIMESTRE' => 'II TRIM', '2 TRIM' => 'II TRIM',
                '3 TRIMESTRE' => 'III TRIM', 'TERCER TRIMESTRE' => 'III TRIM', '3 TRIM' => 'III TRIM',
            ];
            if (isset($map[$upper])) return $map[$upper];
        }

        // Resultado de citología vaginal II trimestre.
        // SIGIRES no acepta NEGATIVO; el catálogo de la guía usa
        // NEGATIVA PARA NEOPLASIA.
        if ($field === 98) {
            $map = [
                'NEGATIVO' => 'NEGATIVA PARA NEOPLASIA',
                'NEGATIVA' => 'NEGATIVA PARA NEOPLASIA',
                'NORMAL' => 'NEGATIVA PARA NEOPLASIA',
                'NEGATIVA PARA MALIGNIDAD' => 'NEGATIVA PARA NEOPLASIA',
            ];
            if (isset($map[$upper])) return $map[$upper];
        }

        // Campo 123: la guía únicamente admite SI o NO y NO es obligatorio.
        // Solo normalizamos abreviaturas inequívocas; fechas, números u otros
        // textos no se convierten en una respuesta clínica inventada.
        if ($field === 123) {
            $map = ['S' => 'SI', 'SI' => 'SI', 'N' => 'NO', 'NO' => 'NO'];
            if (isset($map[$upper])) return $map[$upper];

            if (!in_array($upper, ['SI', 'NO'], true)) return '';
        }

        // Resultado de tamizaje de Chagas: catálogo oficial de la guía.
        if ($field === 148) {
            $map = [
                'NEGATIVO' => 'NO REACTIVO',
                'NO REACTIVA' => 'NO REACTIVO',
                'NO REACTIVO' => 'NO REACTIVO',
                'POSITIVO' => 'REACTIVO',
                'REACTIVA' => 'REACTIVO',
                'REACTIVO' => 'REACTIVO',
                'SIN DATOS' => 'SIN DATO',
                'N/A' => 'SIN DATO',
                'NA' => 'SIN DATO',
            ];
            if (isset($map[$upper])) return $map[$upper];
        }

        return $v;
    }

    private function normalizarComparacion(string $value): string
    {
        return trim($this->sinTildes(mb_strtoupper($value)));
    }

    private function sinTildes(string $value): string
    {
        return strtr($value, [
            'Á'=>'A','É'=>'E','Í'=>'I','Ó'=>'O','Ú'=>'U','Ü'=>'U','Ñ'=>'N',
            'á'=>'A','é'=>'E','í'=>'I','ó'=>'O','ú'=>'U','ü'=>'U','ñ'=>'N',
        ]);
    }

    private function esTipoNumerico(string $type): bool
    {
        $normalized = mb_strtolower(trim($type));

        return str_contains($normalized, 'numer')
            || str_contains($normalized, 'decimal')
            || str_contains($normalized, 'entero');
    }


}