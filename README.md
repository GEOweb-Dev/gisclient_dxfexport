# GISCLIENT DXF EXPORT
> Plugin per l'esportazione delle mappe GisClient in formato DXF 

**Download DXF**
### Request
Per estrarre un file DXF è necessario chiamare il servizio gcExportService.php con i seguenti parametri

##### Parametri
| Parametro | Tipo | Obbligatorio | Descrizione |
| :--- | :--- | :--- | :--- |
| minx | double | true | Coordinata X minima |
| maxx | double | true | Coordinata X massima |
| miny | double | true | Coordinata Y minima |
| maxy | double | true | Coordinata Y massima |
| mapset | string | true | Codici dei mapsets da estrarre, suddivisi da virgola  |
| themes | string | true | Codici identificativi dei temi da estrarre, suddivisi da virgola |
| project | string | true | Codice del progetto che racchiude tutti i mapsets |
| epsg | int | true | Identificativo SRID del sistema di riferimento di estrazione |
| template | string | false | Percorso del modello DXF da utilizzare per il file, contenente i blocchi e i tipi linea |
| enableLineThickness | bool (0/1) | false | Abilita lo spessore della linea (0 -> no spessore, 1-> forza spessore ) |
| enableColors | bool (0/1) | true | Forza colori DALAYER  (0-> colore in base al tema, 1-> colore DALAYER)|
| enableTemplateLayer | bool (0/1) | true | Abilita il raggruppamento dei layer (0-> layer come da mapset, 1-> layer come da raggruppamento custom) |
| textScaleMultiplier | double | true | Moltiplicatore da applicare alla dimensione dei testi con punto |
| labelScaleMultiplier | double | true | Moltiplicatore da applicare alla dimensione delle etichette inserite automaticamente |
| insertScaleMultiplier | double | true | Moltiplicatore da applicare alla dimensione dei blocchi |

### Response
In base alla proprietà _dxfSaveToDir_ il testo del DXF viene inviato direttamente nel corpo della risposta oppure viene inviato un documento JSON con i riferimenti per il download del file.
{
    "filePath": "path relativo per il download",
    "fileName": "nome del file da scaricare"
}
