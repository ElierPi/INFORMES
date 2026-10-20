<?php

use App\Livewire\Informes;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

beforeEach(function () {
    Storage::fake('local');
});

dataset('locked report outputs', [
    'CronicosDusakawiCorrectionUploader.outputPath' => [Informes\CronicosDusakawiCorrectionUploader::class, 'outputPath'],
    'FamiliarColombiaCorreccionUploader.zipCorregidoPath' => [Informes\FamiliarColombiaCorreccionUploader::class, 'zipCorregidoPath'],
    'DusakawiZipCorreccionUploader.zipCorregidoPath' => [Informes\DusakawiZipCorreccionUploader::class, 'zipCorregidoPath'],
    'GestantesCorreccionUploader.informePath' => [Informes\GestantesCorreccionUploader::class, 'informePath'],
    'GestantesCorreccionUploader.erroresPath' => [Informes\GestantesCorreccionUploader::class, 'erroresPath'],
    'GestantesCorreccionUploader.generatedTxtPath' => [Informes\GestantesCorreccionUploader::class, 'generatedTxtPath'],
    'GestantesCorreccionUploader.generatedZipPath' => [Informes\GestantesCorreccionUploader::class, 'generatedZipPath'],
    'GestantesUploader.uploadedPath' => [Informes\GestantesUploader::class, 'uploadedPath'],
    'GestantesUploader.txtPath' => [Informes\GestantesUploader::class, 'txtPath'],
    'GestantesUploader.zipPath' => [Informes\GestantesUploader::class, 'zipPath'],
    'GestantesUploader.correctedFile' => [Informes\GestantesUploader::class, 'correctedFile'],
    'Resolucion1552FamiliarCorrectionUploader.outputPath' => [Informes\Resolucion1552FamiliarCorrectionUploader::class, 'outputPath'],
    'Resolucion1552DusakawiCorrectionUploader.outputPath' => [Informes\Resolucion1552DusakawiCorrectionUploader::class, 'outputPath'],
    'Resolucion1552FamiliarUploader.txtPath' => [Informes\Resolucion1552FamiliarUploader::class, 'txtPath'],
    'Resolucion1552Uploader.zipPath' => [Informes\Resolucion1552Uploader::class, 'zipPath'],
    'Resolucion1552Uploader.txtPath' => [Informes\Resolucion1552Uploader::class, 'txtPath'],
    'Resolucion1552SanitasUploader.txtPath' => [Informes\Resolucion1552SanitasUploader::class, 'txtPath'],
    'Resolucion1552SanitasUploader.zipPath' => [Informes\Resolucion1552SanitasUploader::class, 'zipPath'],
    'Resolucion1552ProtegerUploader.txtPath' => [Informes\Resolucion1552ProtegerUploader::class, 'txtPath'],
    'Resolucion1552ProtegerUploader.zipPath' => [Informes\Resolucion1552ProtegerUploader::class, 'zipPath'],
    'Resolucion1604DusakawiCorrectionUploader.outputPath' => [Informes\Resolucion1604DusakawiCorrectionUploader::class, 'outputPath'],
    'Resolucion1604DusakawiUploader.txtPath' => [Informes\Resolucion1604DusakawiUploader::class, 'txtPath'],
    'Resolucion1604FamiliarCorrectionUploader.txtPath' => [Informes\Resolucion1604FamiliarCorrectionUploader::class, 'txtPath'],
    'Resolucion1604FamiliarUploader.txtPath' => [Informes\Resolucion1604FamiliarUploader::class, 'txtPath'],
    'SigiresCronicosUploader.matrizPath' => [Informes\SigiresCronicosUploader::class, 'matrizPath'],
    'SigiresCronicosUploader.txtPath' => [Informes\SigiresCronicosUploader::class, 'txtPath'],
    'SigiresCronicosUploader.zipPath' => [Informes\SigiresCronicosUploader::class, 'zipPath'],
    'RipsUploader.archivoFuentePath' => [Informes\RipsUploader::class, 'archivoFuentePath'],
    'RipsUploader.resultado' => [Informes\RipsUploader::class, 'resultado.download_path'],
    'RipsUploader.resultadoLote' => [Informes\RipsUploader::class, 'resultadoLote.download_path'],
    'GestanteMensualUploader.resultado' => [Informes\GestanteMensualUploader::class, 'resultado.download_path'],
    'Resolucion202PreparacionUploader.zipPath' => [Informes\Resolucion202PreparacionUploader::class, 'zipPath'],
    'Resolucion202PreparacionUploader.txtPath' => [Informes\Resolucion202PreparacionUploader::class, 'txtPath'],
    'DemandaInducidaUploader.correccionResultado' => [Informes\DemandaInducidaUploader::class, 'correccionResultado.download_path'],
    'DemandaInducidaUploader.resultado' => [Informes\DemandaInducidaUploader::class, 'resultado.download_path'],
    'Informe202Uploader.archivoCorregido' => [Informes\Informe202Uploader::class, 'archivoCorregido'],
    'Informe202Uploader.archivoTxtCorregido' => [Informes\Informe202Uploader::class, 'archivoTxtCorregido'],
]);

test('report output state cannot be overwritten by the client', function (string $componentClass, string $property) {
    $this->actingAs(User::factory()->create());

    Livewire::test($componentClass)
        ->set($property, storage_path('app/private/report-download-test/forged.txt'));
})->with('locked report outputs')->throws(CannotUpdateLockedPropertyException::class);

test('generated reports can still be downloaded with state assigned by the server', function () {
    Storage::fake('local');
    Storage::disk('local')->put('report-download-test/corrected.xlsx', 'synthetic report content');
    $path = Storage::disk('local')->path('report-download-test/corrected.xlsx');

    $component = app(Informes\CronicosDusakawiCorrectionUploader::class);
    $component->generado = true;
    $component->outputPath = $path;
    $component->outputName = 'corregido.xlsx';

    $response = $component->descargarExcel();

    expect($response)->toBeInstanceOf(BinaryFileResponse::class)
        ->and($response->getFile()->getPathname())->toBe($path)
        ->and($response->headers->get('Content-Disposition'))->toContain('corregido.xlsx')
        ->and(file_get_contents($response->getFile()->getPathname()))->toBe('synthetic report content');
});
