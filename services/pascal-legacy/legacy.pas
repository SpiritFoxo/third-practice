program LegacyCSV;

{$mode objfpc}{$H+}

uses
  SysUtils, DateUtils, Process, Unix;

function GetEnvDef(const name, def: string): string;
var
  v: string;
begin
  v := GetEnvironmentVariable(name);
  if v = '' then
    Exit(def)
  else
    Exit(v);
end;

function RandFloat(minV, maxV: Double): Double;
begin
  Result := minV + Random * (maxV - minV);
end;

procedure GenerateAndCopy();
var
  outDir, fn, fullpath, pghost, pgport, pguser, pgdb, copyCmd: string;
  f: TextFile;
  ts: string;
begin
  outDir := GetEnvDef('CSV_OUT_DIR', '/data/csv');
  ts := FormatDateTime('yyyymmdd_hhnnss', Now);
  fn := 'telemetry_' + ts + '.csv';
  fullpath := IncludeTrailingPathDelimiter(outDir) + fn;

  AssignFile(f, fullpath);
  Rewrite(f);
  Writeln(f, 'recorded_at,voltage,temp,source_file');
  Writeln(f, FormatDateTime('yyyy-mm-dd hh:nn:ss', Now) + ',' +
             FormatFloat('0.00', RandFloat(3.2, 12.6)) + ',' +
             FormatFloat('0.00', RandFloat(-50.0, 80.0)) + ',' +
             fn);
  CloseFile(f);

  pghost := GetEnvDef('PGHOST', 'db');
  pgport := GetEnvDef('PGPORT', '5432');
  pguser := GetEnvDef('PGUSER', 'monouser');
  pgdb   := GetEnvDef('PGDATABASE', 'monolith');

  copyCmd :=
    'psql "host=' + pghost +
    ' port=' + pgport +
    ' user=' + pguser +
    ' dbname=' + pgdb +
    '" -c ''\copy telemetry_legacy(recorded_at, voltage, temp, source_file) ' +
    'FROM ''''' + fullpath + ''''' WITH (FORMAT csv, HEADER true)''';

  fpSystem(copyCmd);
end;

var
  period: Integer;
begin
  Randomize;
  period := StrToIntDef(GetEnvDef('GEN_PERIOD_SEC', '300'), 300);
  
  WriteLn('[Pascal] Legacy service started. Generating data every ', period, ' seconds.');

  while True do
  begin
    try
      GenerateAndCopy();
      WriteLn('[Pascal] Data generated and pushed to DB.');
    except
      on E: Exception do
        WriteLn('[Pascal] Error: ', E.Message);
    end;
    Sleep(period * 1000);
  end;
end.