<?php
namespace Csgt\Crud;

use DB;
use Hash;
use View;
use Crypt;
use Input;
use Request;
use Session;
use Redirect;
use Response;

class Crud
{
    private static $showExport = true;
    private static $showSearch = true;
    private static $stateSave  = true;
    private static $softDelete = false;
    private static $responsive = true;
    private static $perPage    = 20;
    private static $tabla;
    private static $tablaId;
    private static $titulo;
    private static $data;
    private static $colSlug       = 'slug';
    private static $slugSeparator = '-';
    private static $camposSlug    = [];
    private static $camposShow    = [];
    private static $camposEdit    = [];
    private static $camposHidden  = [];
    private static $wheres        = [];
    private static $wheresRaw     = [];
    private static $leftJoins     = [];
    private static $botonesExtra  = [];
    private static $orders        = [];
    private static $groups        = [];
    private static $permisos      = ['add' => false, 'edit' => false, 'delete' => false];
    private static $template      = 'template/template';

    public static function getData($showEdit)
    {
        $response = [];
        $dataarr  = [];

        $selects = [];
        $query   = DB::table(self::$tabla);

        if ($showEdit == '1') {
            $campos = self::$camposEdit;
        } else {
            $campos = self::$camposShow;
        }

        foreach ($campos as $campo) {
            $selects[] = $campo['campo'] . ' AS ' . $campo['alias'];
        }
        $selects[] = self::$tabla . '.' . self::$tablaId;

        $query->selectRaw(implode(',', $selects));

        foreach (self::$leftJoins as $leftJoin) {
            $query->leftJoin($leftJoin['tabla'], $leftJoin['col1'], $leftJoin['operador'], $leftJoin['col2']);
        }

        foreach (self::$wheres as $where) {
            $query->where($where['columna'], $where['operador'], $where['valor']);
        }

        foreach (self::$wheresRaw as $whereRaw) {
            $query->whereRaw($whereRaw);
        }
        if (self::$softDelete) {
            $query->whereNull(self::$tabla . '.deleted_at');
        }

        foreach (self::$groups as $group) {
            $query->groupBy($group);
        }

        $registros = $query->count();

        $orders = Input::get('order');
        if ($orders) {
            foreach ($orders as $order) {
                $orderArray = explode(' AS ', $selects[$order['column']]);
                $query->orderBy(DB::raw($orderArray[0]), $order['dir']);
            }
        }

        $columns = Input::get('columns');
        $search  = Input::get('search');
        $i       = 0;
        $query->where(function ($q) use ($columns, $selects, $i, $search) {
            if ($columns) {
                foreach ($columns as $column) {
                    if ($column['searchable']) {
                        $select = explode(' AS ', $selects[$i]);
                        $q->orWhere($select[0], 'like', '%' . $search['value'] . '%');
                    }
                    $i++;
                }
            }
        });

        $filtrados = $query->count();

        $query->skip(Input::get('start'))
            ->take(Input::get('length'));

        $data = $query->get();

        $response['draw']            = Input::get('draw');
        $response['recordsTotal']    = $registros;
        $response['recordsFiltered'] = $filtrados;
        foreach ($data as $d) {
            $tmparr = [];
            foreach ($d as $columna => $valor) {
                if ($columna == self::$tablaId) {
                    $tmparr[] = Crypt::encrypt($valor);
                } else {
                    $tmparr[] = $valor;
                }

            }

            $dataarr[] = $tmparr;
        }

        $response['data'] = $dataarr;

        return Response::json($response);
    }

    public static function setExport($aBool)
    {
        self::$showExport = $aBool;
    }

    public static function setSearch($aBool)
    {
        self::$showSearch = $aBool;
    }

    public static function setSoftDelete($aBool)
    {
        self::$softDelete = $aBool;
    }

    public static function setStateSave($aBool)
    {
        self::$stateSave = $aBool;
    }

    public static function setSlug($aParams)
    {
        $allowed = ['columnas', 'campo', 'separator'];

        foreach ($aParams as $key => $val) {
            //Validamos que todas las variables del array son permitidas.
            if (!in_array($key, $allowed)) {
                dd('setSlug no recibe parametros con el nombre: ' . $key . '! solamente se permiten: ' . implode(', ', $allowed));
            } else {
                if ($key == 'columnas') {
                    foreach ($val as $columnas) {
                        self::$camposSlug[] = $columnas;
                    }

                } elseif ($key == 'campo') {
                    self::$colSlug = $val;
                } elseif ($key == 'separator') {
                    self::$slugSeparator = $val;
                }

            }
        }
    }

    public static function getSoftDelete()
    {
        return self::$softDelete;
    }

    public static function setPerPage($aCuantos)
    {
        self::$perPage = $aCuantos;
    }

    public function setResponsive($aResponsive)
    {
        self::$responsive = $aResponsive;
    }

    public static function setTabla($aTabla)
    {
        self::$tabla = $aTabla;
    }

    public static function setTablaId($aNombre)
    {
        self::$tablaId = $aNombre;
    }

    public static function setTitulo($aNombre)
    {
        self::$titulo = $aNombre;
    }

    public static function setBotonExtra($aParams)
    {
        $allowed = ['url', 'titulo', 'target', 'icon', 'class', 'confirm', 'confirmmessage'];

        foreach ($aParams as $key => $val) {
            //Validamos que todas las variables del array son permitidas.
            if (!in_array($key, $allowed)) {
                dd('setBotonExtra no recibe parametros con el nombre: ' . $key . '! solamente se permiten: ' . implode(', ', $allowed));
            }
        }
        if (!array_key_exists('url', $aParams)) {
            dd('setBotonExtra debe tener un valor para "url"');
        }

        $icon           = (!array_key_exists('icon', $aParams) ? 'glyphicon glyphicon-star' : $aParams['icon']);
        $class          = (!array_key_exists('class', $aParams) ? 'default' : $aParams['class']);
        $titulo         = (!array_key_exists('titulo', $aParams) ? '' : $aParams['titulo']);
        $target         = (!array_key_exists('target', $aParams) ? '' : $aParams['target']);
        $confirm        = (!array_key_exists('confirm', $aParams) ? false : $aParams['confirm']);
        $confirmmessage = (!array_key_exists('confirmmessage', $aParams) ? '¿Estas seguro?' : $aParams['confirmmessage']);

        $arr = [
            'url'            => $aParams['url'],
            'titulo'         => $titulo,
            'icon'           => $icon,
            'class'          => $class,
            'target'         => $target,
            'confirm'        => $confirm,
            'confirmmessage' => $confirmmessage,
        ];
        self::$botonesExtra[] = $arr;
    }

    public static function setHidden($aParams)
    {
        $allowed = ['campo', 'valor'];

        foreach ($aParams as $key => $val) //Validamos que todas las variables del array son permitidas.
        {
            if (!in_array($key, $allowed)) {
                dd('setHidden no recibe parametros con el nombre: ' . $key . '! solamente se permiten: ' . implode(', ', $allowed));
            }
        }

        $arr = [
            'campo' => $aParams['campo'],
            'valor' => $aParams['valor'],
        ];
        self::$camposHidden[] = $arr;
    }

    public static function setOrderBy($aParams)
    {
        $allowed     = ['columna', 'direccion'];
        $direcciones = ['asc', 'desc'];

        foreach ($aParams as $key => $val) //Validamos que todas las variables del array son permitidas.
        {
            if (!in_array($key, $allowed)) {
                dd('setOrderBy no recibe parametros con el nombre: ' . $key . '! solamente se permiten: ' . implode(', ', $allowed));
            }
        }

        $columna   = (!array_key_exists('columna', $aParams) ? 0 : $aParams['columna']);
        $direccion = (!array_key_exists('direccion', $aParams) ? 'asc' : $aParams['direccion']);

        self::$orders[$columna] = $direccion;
    }

    public static function setGroupBy($aCampo)
    {
        self::$groups[] = $aCampo;
    }

    public static function setCampo($aParams)
    {
        $allowed = ['campo', 'nombre', 'editable', 'show', 'tipo', 'class',
            'default', 'reglas', 'reglasmensaje', 'decimales', 'query', 'combokey', 'enumarray', 'filepath', 'filewidth', 'fileheight'];
        $tipos = ['string', 'numeric', 'date', 'datetime', 'bool', 'combobox', 'password', 'enum', 'file', 'securefile', 'image', 'textarea'];

        foreach ($aParams as $key => $val) {
            //Validamos que todas las variables del array son permitidas.
            if (!in_array($key, $allowed)) {
                dd('setCampo no recibe parametros con el nombre: ' . $key . '! solamente se permiten: ' . implode(', ', $allowed));
            }
        }

        if (!array_key_exists('campo', $aParams)) {
            dd('setCampo debe tener un valor para "campo"');
        }

        $nombre        = (!array_key_exists('nombre', $aParams) ? str_replace('_', ' ', ucfirst($aParams['campo'])) : $aParams['nombre']);
        $edit          = (!array_key_exists('editable', $aParams) ? true : $aParams['editable']);
        $show          = (!array_key_exists('show', $aParams) ? true : $aParams['show']);
        $tipo          = (!array_key_exists('tipo', $aParams) ? 'string' : $aParams['tipo']);
        $class         = (!array_key_exists('class', $aParams) ? '' : $aParams['class']);
        $default       = (!array_key_exists('default', $aParams) ? '' : $aParams['default']);
        $reglas        = (!array_key_exists('reglas', $aParams) ? [] : $aParams['reglas']);
        $decimales     = (!array_key_exists('decimales', $aParams) ? 0 : $aParams['decimales']);
        $query         = (!array_key_exists('query', $aParams) ? '' : $aParams['query']);
        $combokey      = (!array_key_exists('combokey', $aParams) ? '' : $aParams['combokey']);
        $reglasmensaje = (!array_key_exists('reglasmensaje', $aParams) ? '' : $aParams['reglasmensaje']);
        $filepath      = (!array_key_exists('filepath', $aParams) ? '' : $aParams['filepath']);
        $filewidth     = (!array_key_exists('filewidth', $aParams) ? 80 : $aParams['filewidth']);
        $fileheight    = (!array_key_exists('fileheight', $aParams) ? 80 : $aParams['fileheight']);
        $enumarray     = (!array_key_exists('enumarray', $aParams) ? [] : $aParams['enumarray']);
        $searchable    = true;

        if (!in_array($tipo, $tipos)) {
            dd('El tipo configurado (' . $tipo . ') no existe! solamente se permiten: ' . implode(', ', $tipos));
        }

        if ($tipo == 'combobox' && ($query == '' || $combokey == '')) {
            dd('Para el tipo combobox el query y combokey son requeridos');
        }

        if ($tipo == 'file' && $filepath == '') {
            dd('Para el tipo file hay que especifiarle el filepath');
        }

        if ($tipo == 'image' && $filepath == '') {
            dd('Para el tipo image hay que especifiarle el filepath');
        }

        if ($tipo == 'securefile' && $filepath == '') {
            dd('Para el tipo securefile hay que especifiarle el filepath');
        }

        if ($tipo == 'emum' && count($enumarray) == 0) {
            dd('Para el tipo enum el enumarray es requerido');
        }

        if (!strpos($aParams['campo'], ')')) {
            $arr = explode('.', $aParams['campo']);
            if (count($arr) >= 2) {
                $campoReal = $arr[count($arr) - 1];
            } else {
                $campoReal = $aParams['campo'];
            }

            $alias = str_replace('.', '__', $aParams['campo']);
        } else {
            $campoReal  = $aParams['campo'];
            $alias      = 'a' . date('U') . count(self::$camposShow); //Nos inventamos un alias para los subqueries
            $searchable = false;
        }

        $arr = [
            'nombre'        => $nombre,
            'campo'         => $aParams['campo'],
            'alias'         => $alias,
            'campoReal'     => $campoReal,
            'tipo'          => $tipo,
            'show'          => $show,
            'editable'      => $edit,
            'default'       => $default,
            'reglas'        => $reglas,
            'reglasmensaje' => $reglasmensaje,
            'class'         => $class,
            'decimales'     => $decimales,
            'query'         => $query,
            'combokey'      => $combokey,
            'searchable'    => $searchable,
            'enumarray'     => $enumarray,
            'filepath'      => $filepath,
            'filewidth'     => $filewidth,
            'fileheight'    => $fileheight,
        ];
        if ($show) {
            self::$camposShow[] = $arr;
        }

        if ($edit) {
            self::$camposEdit[] = $arr;
        }

    }

    public static function setWhere($aColumna, $aOperador, $aValor = null)
    {
        if ($aValor == null) {
            $aValor    = $aOperador;
            $aOperador = '=';
        }

        self::$wheres[] = ['columna' => $aColumna, 'operador' => $aOperador, 'valor' => $aValor];
    }

    public static function setWhereRaw($aStatement)
    {
        self::$wheresRaw[] = $aStatement;
    }

    public static function setLeftJoin($aTabla, $aCol1, $aOperador, $aCol2)
    {
        self::$leftJoins[] = ['tabla' => $aTabla, 'col1' => $aCol1, 'operador' => $aOperador, 'col2' => $aCol2];
    }

    public static function setPermisos($aPermisos)
    {
        self::$permisos = $aPermisos;
    }

    public static function setTemplate($aTemplate)
    {
        self::$template = $aTemplate;
    }

    private static function getUrl($aPath, $aEdit = false)
    {
        $arr = explode('/', $aPath);
        array_pop($arr);
        if ($aEdit) {
            array_pop($arr);
        }

        $route = implode('/', $arr);

        return $route;
    }

    private static function getGetVars()
    {
        $getVars    = Request::server('QUERY_STRING');
        $nuevasVars = '';
        if ($getVars != '') {
            $nuevasVars = '?' . $getVars;
        }

        return $nuevasVars;
    }

    public static function index()
    {
        if (self::$tabla == '') {
            dd('setTabla es obligatorio.');
        }

        if (self::$tablaId == '') {
            dd('setTablaId es obligatorio.');
        }

        return View::make('crud::index')
            ->with('stateSave', self::$stateSave)
            ->with('template', self::$template)
            ->with('showExport', self::$showExport)
            ->with('showSearch', self::$showSearch)
            ->with('perPage', self::$perPage)
            ->with('titulo', self::$titulo)
            ->with('columnas', self::$camposShow)
            ->with('permisos', self::$permisos)
            ->with('orders', self::$orders)
            ->with('botonesExtra', self::$botonesExtra)
            ->with('nuevasVars', self::getGetVars())
            ->with('responsive', self::$responsive);
    }

    public static function create($aId)
    {
        $data = null;
        $hijo = 'Nuevo';

        if (!$aId == 0) {
            $data = DB::table(self::$tabla)
                ->where(self::$tablaId, Crypt::decrypt($aId))
                ->first();
            $hijo = 'Editar';
            $path = self::getUrl(Request::path(), true);
        } else {
            $path = self::getUrl(Request::path(), false);
        }

        $route = str_replace($aId, '', $path);

        $combos = null;
        foreach (self::$camposEdit as $campo) {
            if ($campo['tipo'] == 'combobox') {
                $resultados = DB::select(DB::raw($campo['query']));
                $temp       = [];
                foreach ($resultados as $resultado) {
                    $i = 0;
                    foreach ($resultado as $columna) {
                        if ($i == 0) {
                            $nombre = $columna;
                        } else {
                            $id = $columna;
                        }

                        $i++;
                    }

                    $temp[$id] = $nombre;
                }
                $combos[$campo['alias']] = $temp;
            }
        }

        return View::make('crud::edit')
            ->with('pathstore', self::getUrl(Request::path(), false))
            ->with('template', self::$template)
            ->with('breadcrum', ['padre' => ['titulo' => self::$titulo, 'ruta' => $path], 'hijo' => $hijo])
            ->with('columnas', self::$camposEdit)
            ->with('data', $data)
            ->with('combos', $combos)
            ->with('nuevasVars', self::getGetVars());
    }

    public static function store($id = null)
    {
        $data          = [];
        $slug          = '';
        $no_permitidas = ["á", "é", "í", "ó", "ú", "Á", "É", "Í", "Ó", "Ú", "ñ", "À", "Ã", "Ì", "Ò", "Ù", "Ã™", "Ã ", "Ã¨", "Ã¬", "Ã²", "Ã¹", "ç", "Ç", "Ã¢", "ê", "Ã®", "Ã´", "Ã»", "Ã‚", "ÃŠ", "ÃŽ", "Ã”", "Ã›", "ü", "Ã¶", "Ã–", "Ã¯", "Ã¤", "«", "Ò", "Ã", "Ã„", "Ã‹"];
        $permitidas    = ["a", "e", "i", "o", "u", "A", "E", "I", "O", "U", "n", "N", "A", "E", "I", "O", "U", "a", "e", "i", "o", "u", "c", "C", "a", "e", "i", "o", "u", "A", "E", "I", "O", "U", "u", "o", "O", "i", "a", "e", "U", "I", "A", "E"];

        foreach (self::$camposEdit as $campo) {
            if ($campo['tipo'] == 'bool') {
                $data[$campo['campoReal']] = Input::get($campo['campoReal'], 0);
            } else if ($campo['tipo'] == 'combobox') {
                if (Input::get($campo['combokey']) == '') {
                    $data[$campo['combokey']] = null;
                } else {
                    $data[$campo['combokey']] = Input::get($campo['combokey']);
                }

            } else if ($campo['tipo'] == 'date') {
                $laFecha = explode('/', Input::get($campo['campoReal']));
                if (count($laFecha) == 3) {
                    $data[$campo['campoReal']] = $laFecha[2] . '-' . $laFecha[1] . '-' . $laFecha[0];
                } else {
                    $data[$campo['campoReal']] = null;
                }
            } else if ($campo['tipo'] == 'datetime') {
                $fechaHora = explode(' ', Input::get($campo['campoReal']));
                if (count($fechaHora) == 2) {
                    $laFecha = explode('/', $fechaHora[0]);
                    if (count($laFecha) != 3) {
                        $data[$campo['campoReal']] = null;
                    } else {
                        $data[$campo['campoReal']] = $laFecha[2] . '-' . $laFecha[1] . '-' . $laFecha[0] . ' ' . $fechaHora[1];
                    }
                } else {
                    $data[$campo['campoReal']] = null;
                }
            } else if ($campo['tipo'] == 'password') {
                if ($id == null) {
                    $data[$campo['campoReal']] = Hash::make(Input::get($campo['campoReal']));
                } else {
                    if (Input::get($campo['campoReal']) != '') {
                        $data[$campo['campoReal']] = Hash::make(Input::get($campo['campoReal']));
                    }

                }
            } else if (($campo['tipo'] == 'file') || ($campo['tipo'] == 'image')) {
                if (Input::hasFile($campo['campoReal'])) {
                    $file = Input::file($campo['campoReal']);

                    $filename = date('Ymdhis') . mt_rand(1, 1000) . '.' . strtolower($file->getClientOriginalExtension());
                    $path     = public_path() . $campo['filepath'];

                    if (!file_exists($path)) {
                        mkdir($path, 0777, true);
                    }

                    $file->move($path, $filename);

                    $data[$campo['campoReal']] = $filename;
                }
            } else if ($campo['tipo'] == 'securefile') {
                if (Input::hasFile($campo['campoReal'])) {
                    $file     = Input::file($campo['campoReal']);
                    $filename = date('Ymdhis') . mt_rand(1, 1000) . '.' . strtolower($file->getClientOriginalExtension());
                    $path     = $campo['filepath'];

                    if (!file_exists($path)) {
                        mkdir($path, 0777, true);
                    }

                    $file->move($path, $filename);

                    $data[$campo['campoReal']] = $filename;
                }
            } else {
                $data[$campo['campoReal']] = Input::get($campo['campoReal']);
            }

            if (in_array($campo['campoReal'], self::$camposSlug)) {
                $temp = strtolower(Input::get($campo['campoReal']));
                $temp = str_replace(' ', self::$slugSeparator, $temp);
                $temp = str_replace('\\', 'y', $temp);
                $temp = str_replace('+', 'y', $temp);
                $temp = str_replace('-', '', $temp);
                $temp = str_replace('\'', '', $temp);
                $temp = str_replace($no_permitidas, $permitidas, $temp);
                $slug .= $temp;
            }
        }

        if ($slug != '' && $id == null) {
            $result = DB::table(self::$tabla)->where(self::$colSlug, $slug)->first();
            if (!$result) {
                $data[self::$colSlug] = $slug;
            } else {

                $i = 1;
                while ($result) {
                    $i++;
                    $result = DB::table(self::$tabla)->where(self::$colSlug, $slug . self::$slugSeparator . $i)->first();
                }

                $data[self::$colSlug] = $slug . self::$slugSeparator . $i;
            }

        }

        $data['updated_at'] = date_create();

        foreach (self::$camposHidden as $campo) {
            $data[$campo['campo']] = $campo['valor'];
        }

        if ($id == null) {
            $data['created_at'] = date_create();
            try {
                $query = DB::table(self::$tabla)
                    ->insert($data);
                Session::flash('message', 'Registro creado exitosamente');
                Session::flash('type', 'success');
            } catch (\Exception $e) {
                Session::flash('message', 'Error actualizando registro: ' . $e->getMessage());
                Session::flash('type', 'danger');
            }

            return Redirect::to(Request::path() . self::getGetVars());
        } else {
            try {
                $query = DB::table(self::$tabla)
                    ->where(self::$tablaId, Crypt::decrypt($id))
                    ->update($data);

                Session::flash('message', 'Registro actualizado exitosamente');
                Session::flash('type', 'success');
            } catch (\Exception $e) {
                Session::flash('message', 'Error actualizando registro: ' . $e->getMessage());
                Session::flash('type', 'danger');
            }

            return Redirect::to(self::getUrl(Request::path(), false) . self::getGetVars());
        }
    }

    public static function destroy($aId)
    {
        try {
            if (self::$softDelete) {
                $query = DB::table(self::$tabla)
                    ->where(self::$tablaId, Crypt::decrypt($aId))
                    ->update(['deleted_at' => date_create()]);
            } else {
                $query = DB::table(self::$tabla)
                    ->where(self::$tablaId, Crypt::decrypt($aId))
                    ->delete();
            }

            Session::flash('message', 'Registro borrado exitosamente');
            Session::flash('type', 'warning');

        } catch (\Exception $e) {
            Session::flash('message', 'Error al borrar campo. Revisar datos relacionados.');
            Session::flash('type', 'danger');
        }

        return Redirect::to(self::getUrl(Request::path(), false) . '?' . Request::server('QUERY_STRING'));
    }
}
