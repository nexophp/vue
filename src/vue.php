<?php

/**
 * vue类
 * 用于生成vue2或vue3的JS代码
 * @author sunkangchina <68103403@qq.com>
 * @license MIT <https://mit-license.org/>
 * @date 2025
 */

class Vue
{
    // 2023-11-01 之前的时间将无法在日期字段中选择
    public $start_date = '';
    /**
     * vue版本，默认为2
     * 当为3时，请加载vue3的JS，如 https://unpkg.com/vue@3/dist/vue.global.js
     */
    public $version = 2;
    /*
    * $config['vue_encodejs'] = true;
    * $config['vue_encodejs_ignore'] = ['/plugins/config/config.php'];
    * 依赖 yarn add --dev javascript-obfuscator
    */
    public $encodejs = false;
    public $upload_url = '/admin/upload/index';
    /**
     * 生成编辑器HTML
     */
    public static $_editor;
    public $after_save = [];
    public $editor_timeout = 600;
    public $page_url;
    public $add_url;
    public $edit_url;
    public $id   = "#app";
    //默认加载方法load()
    public $load_name   = "load";
    public $data = [
        "is_show" => false,
        'where' => "{per_page:20}",
        'lists' => "[]",
        'page'  => "1",
        'total' => 0,
        'form'  => "{}",
        'node'  => "{}",
        'row'   => "{}",
        'loading' => true,
    ];
    public $page_data = [
        "is_show" => false,
        'where'   => "{per_page:20}",
        'lists'   => "[]",
        'page'    => "1",
        'total'   => 0,
        'form'    => "js:{}",
        'node'    => "js:{}",
        'res'     => "js:{}",
        'loading' => true,
    ];
    public $watch = [];
    public $mounted = [];
    public $created_js = [];
    public $methods    = [];
    public $create_update_load = [];
    public $pageMethod = [
        'page_size_change(val)' => "this.where.page= 1;this.where.per_page = val;this.load();",
        'page_change(val)'      => "this.where.page = val;this.load();",
    ];
    public $resetMethod = [
        'reload()' => "this.where.page = 1;this.loading=true;this.load();",
        'reset()'  => "this.where = {};this.loading=true;this.load();",
    ];
    public $addMethod = '';
    public $editMethod = '';
    public $tree_field = 'pid';
    public $treeMethod = [
        'select_click(data)' => " 
            this.node = data;
            this.form.pid = data.id;
            this.form._pid_name = data.label;
            this.\$refs['pid'].\$el.click();
       "
    ];
    public $data_form;
    //搜索的时间
    public $search_date = [];
    public $use_config = true;
    /**
     * construct
     */
    public function __construct()
    {
        global $config;
        if (isset($config['vue_encodejs'])) {
            $this->encodejs = $config['vue_encodejs'];
        }
        if (isset($config['vue_version'])) {
            $v = $config['vue_version'];
            if (in_array($v, [2, 3])) {
                $this->version = $v;
            }
        }
        if (function_exists("do_action")) {
            $version = $this->version;
            if ($version && in_array($version, [2, 3])) {
                $this->version = $version;
            }
        }
        $this->upload_url = $config['upload_url'] ?: $this->upload_url;
    }
    /**
     * form字段
     */
    public function formData($key, $val)
    {
        $this->data_form[$key] = $val;
    }
    /**
     * 定义data
     * $vue->data("object","js:{}");
     * $vue->data("arr","[]");
     * $vue->data("aa",500);
     * $vue->data("bb",'
     * {
     *     s:1,
     *     ss:3
     * }
     * ');
     * $vue->data("true",true);
     * $vue->data("false",false);
     * $vue->data("json",json_encode(['a'=>1]));
     * $vue->data("json1",['a','b']);
     */
    public function data($key, $val)
    {
        $this->data[$key] = $val;
    }
    /**
     * 解析data
     */
    protected function parse_data($val)
    {
        if ($val == '{}' || $val == '[]') {
            return "js:" . $val;
        }
        if (!is_array($val)) {
            $is_json = json_decode($val, true);
            if (is_array($is_json)) {
                $val = $is_json;
            }
        }
        if (is_array($val)) {
        } elseif (is_string($val) && substr($val, 0, 3) != 'js:') {
            $trim = trim($val);
            $trim = str_replace("\n", "", $trim);
            $trim = str_replace(" ", "", $trim);
            if (substr($trim, 0, 1) == '{' || substr($trim, 0, 1) == '[') {
                $val = "js:" . $val;
            }
        }
        return $val;
    }
    /**
     * 支持method
     */
    public function method($name, $val)
    {
        if (strpos($name, '(') === false) {
            $name = $name . "()";
        }
        $this->methods[$name] = $val;
    }
    /**
     * 支持watch
     */
    public function watch($name, $val)
    {
        if (strpos($name, '.') !== false) {
            $name = "'" . $name . "'";
        }
        $this->watch[$name] = $val;
    }

    public function afterSave($val)
    {
        $this->after_save[] = $val;
    }
    /**
     * $vue->mounted('',"js:
     *     const that = this
     *     window.onresize = () => {
     *      return (() => {
     *       that.height = that.\$refs.tableCot.offsetHeight;
     *     })()
     *    }
     *");
     */
    public function mounted($name, $val)
    {
        $this->mounted[$name] = $val;
    }

    /**
     * 支持created(['load()'])
     */
    public function created($methods)
    {
        if (is_array($methods)) {
            foreach ($methods as $v) {
                $k = $v;
                $k = str_replace("(", "", $k);
                $k = str_replace(")", "", $k);
                $k = trim($k);
                $this->created_js[$k] = $k . "()";
            }
        } elseif (is_string($methods)) {
            $k = $v = $methods;
            $k = str_replace("(", "", $k);
            $k = str_replace(")", "", $k);
            $k = trim($k);
            $this->created_js[$k] = $k . "()";
        }
    }
    /**
     * 生成vue js代码
     */
    public function run()
    {
        global $config;
        $this->init();
        $this->runEditor();
        $this->data['_vue_message'] = false;
        $data    = php_to_js($this->data);
        $created = "";
        foreach ($this->created_js as $v) {
            $created .= "this." . $v . ";";
        }
        $methods_str = "";
        $watch_str = "";
        $mounted_str = "";
        $br = "";
        $br2 = "";
        if (!isset($this->methods["load_common()"])) {
            $this->methods["load_common()"] = "js:{}";
        }
        if (!$this->methods['indexMethod(index)']) {
            $this->method('indexMethod(index)', '
                let per_page = this.where.page_size||0;
                let cpage = this.where.page || 1;
                return (cpage - 1) * per_page + index + 1;  
            ');
        }
        if (!$this->methods['index_method(index)']) {
            $this->method('index_method(index)', '
                let per_page = this.where.page_size||0;
                let cpage = this.where.page || 1;
                return (cpage - 1) * per_page + index + 1;  
            ');
        }
        foreach ($this->methods as $k => $v) {
            $v = str_replace("js:", "", $v);
            $this->parse_v($k, $v);
            $methods_str .= $br . $k . php_to_js($v) . ",";
        }
        foreach ($this->watch as $k => $v) {
            $v = str_replace("js:", "", $v);
            $this->parse_v($k, $v);
            $watch_str .= $br . $k . php_to_js($v) . ",";
        }
        foreach ($this->mounted as $k => $v) {
            if (is_string($v) && substr($v, 0, 3) != 'js:') {
                $v = "js:" . $v . "";
            }
            $mounted_str .= $br . php_to_js($v) . "";
        }
        $js = "
            var _this,app;
            new Vue({
                el:'" . $this->id . "',
                data:" . $data . ",
                created(){
                    _this = app =  this;
                    " . $created . "
                },
                mounted(){
                    " . $mounted_str . "
                },
                watch: {
                    " . $watch_str . "
                },
                methods:{" . $methods_str . "$br2}
            }); 
        ";
        if ($this->version == 3) {
            $js = "
                var _this,app;
                const { createApp } = Vue;
                app = createApp({ 
                    data(){ return " . $data . "},
                    created(){
                        _this = this;
                        " . $created . "
                    },
                    mounted(){
                        " . $mounted_str . "
                    },
                    watch: {
                        " . $watch_str . "
                    },
                    methods:{" . $methods_str . "$br2}
                });
                if(typeof ElementPlus !== 'undefined'){
                    app.use(ElementPlus);    
                }                
                app.mount('" . $this->id . "');  
            ";
        }
        $vars = '';
        $e = self::$_editor;
        if ($e) {
            foreach ($e as $name) {
                $vars .= " var editor" . $name . ";\n";
            }
        }
        $code = $vars . $js;
        $name = $this->load_name;
        if ($name && $name != 'load') {
            $code = str_replace("this.load()", "this." . $name . "()", $code);
        }
        if ($this->encodejs) {
            $uri = $_SERVER['REQUEST_URI'];
            $js_file = '/assets/dist/vue/' . md5($uri) . '.js';
            $output_path = PATH;
            if (defined('WWW_PATH')) {
                $output_path = WWW_PATH;
            }
            $js_file_path = $output_path . $js_file;
            $dir = get_dir($js_file_path);
            if (!is_dir($dir)) {
                mkdir($dir, 0777, true);
            }
            $is_write = true;
            $ignore_encode = $config['vue_ignore'] ?: [];
            foreach ($ignore_encode as $v) {
                if (strpos($uri, $v) !== false) {
                    $is_write = false;
                    break;
                }
            }
            if ($is_write) {
                if (!file_exists($js_file_path)) {
                    file_put_contents($js_file_path, $code);
                    $obfuscator_bin = $config['obfuscator'] ?: PATH . 'node_modules/javascript-obfuscator/bin/javascript-obfuscator';
                    $run_cmd = $obfuscator_bin . " $js_file_path --output $js_file_path --self-defending true --disable-console-output true --debug-protection true --control-flow-flattening true --dead-code-injection ture --string-array true --string-array-rotate true --string-array-shuffle true --string-array-index-shift true";
                    exec($run_cmd);
                }
                $rand = filemtime($js_file_path);
                return " 
                (function() {
                  var vue_php_auto = document.createElement('script');
                  vue_php_auto.src = '" . $js_file . "?v=" . $rand . "';
                  document.body.insertBefore(vue_php_auto, document.body.lastChild);
                })();";
            } else {
                return $code;
            }
        }
        if (function_exists('do_action')) {
            do_action("vue", $code);
        }
        return $code;
    }
    /**
     * 解析value
     */
    protected function parse_v(&$k, &$v)
    {
        $t_v = trim($v);
        if (strpos($k, '(') === false && substr($k, -1) != ':') {
            $k = $k . ':';
        }
        if (substr($t_v, 0, 2) == '{{') {
            $v = substr($t_v, 2, -2);
        }
        if (substr($t_v, 0, 1) != '{') {
            $v = "{" . $v . "}";
        }
        if (is_string($v) && substr($v, 0, 3) != 'js:') {
            $v = "js:" . $v;
        }
    }
    /**
     * init
     */
    public function init()
    {
        $data_form_add = '';
        $data_form_update = '';
        if ($this->data_form) {
            $form = [];
            foreach ($this->data_form as $k => $v) {
                $v = $this->parse_data($v);
                $val  = php_to_js($v);
                $data_form_add .= " 
                     this.\$set(this.form,'" . $k . "',$val);\n   
                ";
                $data_form_update .= "
                    if(!row.$k){
                        this.\$set(this.form,'" . $k . "',$val);\n    
                    }                    
                ";
                $form[$k] = $v;
            }
            $this->data['form'] = $form;
        }

        foreach ($this->data as $k => $vv) {
            $this->data[$k] = $this->parse_data($vv);
        }
    }
    /**
     * 支持crud
     */
    public function crud()
    {
        if ($this->page_url) {
            $this->method('load()', "js:ajax('" . $this->page_url . "',this.where,function(res) { 
                app.page   = res.current_page;
                app.total  = res.total;
                app.lists  = res.data;
                app.res  = res;
                if(app.loading){ 
                   app.loading = false; 
                }
            });");
        } else {
        }
        $after_save_str = '';
        if ($this->after_save) {
            foreach ($this->after_save as $v) {
                if ($v) {
                    $v = trim($v);
                    $after_save_str .= $v;
                }
            }
        }
        if ($this->add_url || $this->edit_url) {
            $this->method("save()", "js:let url = '" . $this->add_url . "';
                if(this.form.id){
                    url = '" . $this->edit_url . "';
                } 
                ajax(url,this.form,function(res){ 
                        console.log(res);
                        app.\$message({
                          message: res.msg||res.message,
                          type: res.type
                        }); 
                        if(res.code == 0){
                            app.is_show    = false; 
                            app.load();
                        }
                        " . $after_save_str . "
                }); 
            ");
        } else {
        }
    }
    /**
     * wangeditor5
     */
    public function editor($name = 'body')
    {
        self::$_editor[] = $name;
        return '<div id="' . $name . 'editor—wrapper" class="editor—wrapper">
            <div id="' . $name . 'weditor-tool" class="toolbar-container"></div>
            <div id="' . $name . 'weditor" class="editor-container"  style="height:300px;"></div>
        </div> ';
    }
    /**
     * 运行编辑器
     */
    protected function runEditor()
    {
        if (!self::$_editor) {
            return;
        }
        $js = '';
        foreach (self::$_editor as $name) {
            $code =  "
                parent.layer.closeAll();  
                if(data.url){
                    parent._editorInstances['" . $name . "'].insertNode({
                        type: 'image',
                        src: data.url,
                        children: [{ text: '' }]
                    });
                }
            ";
            $code = aes_encode($code);
            $js .= " 
                if (!window._editorInstances) window._editorInstances = {}; 
                if (window._editorInstances['" . $name . "']) {
                    return;
                }
                var editor_config_" . $name . " = {
                    placeholder: '',
                    MENU_CONF: {
                        uploadImage: {
                            fieldName: 'file',
                            customBrowseAndUpload: function(insertFn) {
                                layer.open({
                                    type: 2,
                                    title: '" . lang('上传图片') . "',
                                    area: ['90%', '80%'],
                                    content: '/admin/media/index?js=" . $code . "'
                                }); 
                                return;
                            },   
                        }
                    },
                    onChange(editor) { 
                        app.form." . $name . " = editor.getHtml();
                    }
                };

                var toolbar_config_" . $name . " = {
                    toolbarKeys: [
                        'fontFamily',
                        'fontSize',
                        'color',
                        'bgColor',
                        'bold',
                        'italic',
                        'uploadImage'
                    ]
                };
                
                window._editorInstances['" . $name . "'] = E.createEditor({
                    selector: '#" . $name . "weditor',
                    config: editor_config_" . $name . ",
                    mode: 'simple'
                });
                 
                var toolbar_" . $name . " = E.createToolbar({
                    editor: window._editorInstances['" . $name . "'],
                    selector: '#" . $name . "weditor-tool',
                    config: toolbar_config_" . $name . "
                }); 
            ";
        }
        $this->method("init_editor()", $js);
        $this->method("open_editor()", " 
            if (document.querySelector('#" . $name . "weditor')) {  
                app.init_editor();    
            }
        ");
        $this->mounted('mounted_editor', "  
            this.open_editor(); 
        ");

        $this->method('update_editor(name,value)', "
           setTimeout(()=>{
                window._editorInstances[name].setHtml(value);
           },500);
        ");
    }
    /**
     * 日期区间：
     * <el-date-picker @change="reload" v-model="where.date" value-format="yyyy-MM-dd" :picker-options="pickerOptions" size="medium" type="daterange" range-separator="至" start-placeholder="开始日期" end-placeholder="结束日期">
     * </el-date-picker>
     *
     * $date    = g('date');
     * if ($date[0]) {
     *    $where['created_at[>]'] = date("Y-m-d 00:00:00", strtotime($date[0]));
     * }
     * if ($date[1]) {
     *    $where['created_at[<=]'] =  date("Y-m-d 23:59:59", strtotime($date[1]));
     * }
     */
    public function addDate()
    {
        $this->addDateTimeSelect();
    }
    /**
     * 重置时间字段
     */
    public function resetDate()
    {
        $this->method("resetDate()", "
            this.pickerOptions = " . php_to_js($this->getDateArea()) . ";
        ");
    }
    /**
     * 排序
     * misc('sortable');
     * $vue->sort(".sortable1 tbody","app.form.xcx_banner");
     */
    public function sort($element, $change_obj = 'lists_sort', $options = [])
    {
        $success  = $options['success'];
        $ajax_url = $options['ajax_url'];
        $sortable = "sortable" . mt_rand(1000, 9999);
        $this->mounted('', "js:this." . $sortable . "();");
        $this->method($sortable . "()", "js:  
          Sortable.create(document.querySelector('" . $element . "'),{     
            handle:'.handler', 
            onEnd(eve) {   
                  let newIndex = eve.newIndex;
                  let oldIndex = eve.oldIndex;   
                  let list = app." . $change_obj . "; 
                  let old = list[oldIndex]; 
                  //删除老数组 
                  list.splice(oldIndex,1);
                  list.splice(newIndex,0,old); 
                  let dd = [];
                  for(let i in list){ 
                    dd.push({id:list[i].id});
                  }
                  ajax('" . $ajax_url . "',{
                    data:dd,
                    page:app.page,
                    per_page:app.where.per_page,
                    total:app.total,
                    },function(res){
                      " . $success . "
                  }); 
            }
          });
        ");
    }
    /**
     * 输出element el-pager
     *
     * @param $load  load或load()
     * @param $where 分页传参数
     * @param $arr   el-pager参数 ['@size-change'=>'',':page-sizes']
     */
    public function pager($load = 'load', $where = 'where', $arr = [])
    {
        if (!$arr['@size-change']) {
            $arr['@size-change'] = 'page_size_change';
        }
        if (substr($load, -1) != ')') {
            $load = $load . "()";
        }
        $this->method($arr['@size-change'] . "(val)", " 
            this." . $where . ".page= 1;
            this." . $where . ".per_page = val;
            this." . $load . ";
         ");

        if (!$arr['@current-change']) {
            $arr['@current-change'] = 'page_change';
        }
        $this->method($arr['@current-change'] . "(val)", " 
            this." . $where . ".page = val;
            this." . $load . ";
        ");
        if (!$arr[':page-sizes']) {
            $page_size_array = get_config('page_size_array') ?: [10, 20, 50, 100, 1000];
            $arr[':page-sizes'] = json_encode($page_size_array);
        }
        if (!$arr[':current-page']) {
            $arr[':current-page'] = $where . '.page';
        }
        if (!$arr[':page-size']) {
            $arr[':page-size'] = $where . '.per_page';
        }
        if (!$arr['layout']) {
            $arr['layout'] = 'total, sizes, prev, pager, next, jumper';
        }
        if (!$arr[':total']) {
            $arr[':total'] = 'total';
        }
        if (!$arr['background']) {
            $arr['background'] = '';
        }

        $attr = '';
        foreach ($arr as $k => $v) {
            if ($v) {
                $attr .= $k . "='" . $v . "' ";
            } else {
                $attr .= $k . $v . " ";
            }
            $attr .= "\n";
        }
        return '<el-pagination ' . $attr . '></el-pagination>' . "\n";
    }

    public function addDateTimeSelect()
    {
        $this->data['pickerOptions'] = $this->getDateArea();
    }
    protected function getDateRangeFlag($a, $b, $allow_1)
    {
        if (!$allow_1 ||  ($allow_1 && $a >= $allow_1 && $b >= $allow_1)) {
            return true;
        }
    }
    /**
     * 定时加载时间选择
     */
    public function reloadPicker($url, $time = 0)
    {
        global $vue;
        if ($time > 0) {
            $time = $time * 1000;
            $this->resetDate();
            $this->created(['interval_picker_options()']);
            $this->method("interval_picker_options()", " 
                setInterval(()=>{
                    ajax('" . $url . "',{},function(res){
                        app.pickerOptions = res.data;
                    });
                }," . $time . ");
            ");
        }
    }
    /**
     * 设置时间选择区间
     */
    public function getDateArea()
    {
        $search_date = $this->search_date;
        $start_date = $this->start_date;
        $output[lang('今天')] = "
            let start = new Date('" . date('Y-m-d', time()) . "'); 
            let end  = new Date('" . date('Y-m-d', time()) . "'); 
            picker.\$emit('pick', [end, end]);
        ";
        $output[lang('昨天')] = "
            let start = new Date('" . date('Y-m-d', time() - 86400) . "'); 
            let end  = new Date('" . date('Y-m-d', time() - 86400) . "'); 
            picker.\$emit('pick', [start, start]);
        ";
        $start = date("Y-m-d", strtotime('this week'));
        $end = date('Y-m-d', time());
        if ($this->getDateRangeFlag($start, $end, $start_date)) {
            $output[lang('本周')] = "           
                let start = new Date('" . $start . "'); 
                let end   = new Date('" . $end . "'); 
                picker.\$emit('pick', [start, end]);
            ";
        }
        $start = date("Y-m-d", strtotime('last week monday'));
        $end = date('Y-m-d', strtotime('-10 second', strtotime('last week sunday +1 day')));
        if ($this->getDateRangeFlag($start, $end, $start_date)) {
            $output[lang('上周')] = "
                let start = new Date('" . $start . "'); 
                let end   = new Date('" . $end . "'); 
                picker.\$emit('pick', [start, end]);
            ";
        }
        $start = date("Y-m-d", strtotime('-2 weeks', strtotime('monday this week')));
        $end = date('Y-m-d', strtotime('-1 week -1 second', strtotime('monday this week')));
        if ($this->getDateRangeFlag($start, $end, $start_date)) {
            $output[lang('上上周')] = "
                let start = new Date('" . $start . "'); 
                let end   = new Date('" . $end . "'); 
                picker.\$emit('pick', [start, end]);
            ";
        }
        $start = date("Y-m-01");
        $end = date("Y-m-d");
        if ($this->getDateRangeFlag($start, $end, $start_date)) {
            $output[lang('本月')] = "
                let start = new Date('" . $start . "');
                let end = new Date('" . $end . "'); 
                picker.\$emit('pick', [start, end]);
            ";
        }
        $start = date("Y-m-d", strtotime('first day of last month'));
        $end = date("Y-m-d", strtotime('last day of last month'));
        if ($this->getDateRangeFlag($start, $end, $start_date)) {
            $output[lang('上月')] = "
                let start = new Date('" . $start . "'); 
                let end = new Date('" . $end . "'); 
                picker.\$emit('pick', [start, end]);
            ";
        }
        $start = date("Y-m-d", strtotime('-2 months', strtotime('first day of this month')));
        $end = date("Y-m-d", strtotime('-1 day', strtotime('first day of last month')));
        if ($this->getDateRangeFlag($start, $end, $start_date)) {
            $output[lang('上上月')] = "
                let start = new Date('" . $start . "'); 
                let end = new Date('" . $end . "'); 
                picker.\$emit('pick', [start, end]);
            ";
        }
        $start = date("Y-m-d", strtotime('-1 month') + 86400);
        $end = date('Y-m-d');
        if ($this->getDateRangeFlag($start, $end, $start_date)) {
            $output[lang('最近一个月')] = "
                let start = new Date('" . $start . "'); 
                let end = new Date('" . $end . "'); 
                picker.\$emit('pick', [start, end]);
            ";
        }
        $start = date("Y-m-d", strtotime('-2 month') + 86400);
        $end = date('Y-m-d');
        if ($this->getDateRangeFlag($start, $end, $start_date)) {
            $output[lang('最近两个月')] = "
                let start = new Date('" . $start . "'); 
                let end = new Date('" . $end . "'); 
                picker.\$emit('pick', [start, end]);
            ";
        }
        $start = date("Y-m-d", strtotime('-3 month') + 86400);
        $end = date('Y-m-d');
        if ($this->getDateRangeFlag($start, $end, $start_date)) {
            $output[lang('最近三个月')] = "
                let start = new Date('" . $start . "'); 
                let end = new Date('" . $end . "'); 
                picker.\$emit('pick', [start, end]);
            ";
        }
        $jidu = vue_get_jidu();
        foreach ($jidu as $k => $v) {
            if ($v['flag']) {
                $output[$k] = " 
                    picker.\$emit('pick', ['" . $v[0] . "', '" . $v[1] . "']);
                ";
            }
        }
        $start = date("Y-m-d", strtotime('first day of January'));
        $end = date("Y-m-d", strtotime('last day of December'));
        if ($this->getDateRangeFlag($start, $end, $start_date)) {
            $output[lang('本年')] = "
                const start = new Date('" . $start . "');
                const end = new Date('" . $end . "'); 
                picker.\$emit('pick', [start, end]);
            ";
        }
        $start = date("Y-m-d", strtotime('first day of January last year'));
        $end = date("Y-m-d", strtotime('last day of December  last year'));
        if ($this->getDateRangeFlag($start, $end, $start_date)) {
            $output[lang('上年')] = "
                const start = new Date('" . $start . "');
                const end = new Date('" . $end . "'); 
                picker.\$emit('pick', [start, end]);
            ";
        }
        $start = date("Y-m-d", mktime(0, 0, 0, 1, 1, date('Y') - 2));
        $end = date("Y-m-d", mktime(23, 59, 59, 12, 31, date('Y') - 2));
        if ($this->getDateRangeFlag($start, $end, $start_date)) {
            $output[lang('上上年')] = "
                const start = new Date('" . $start . "');
                const end = new Date('" . $end . "'); 
                picker.\$emit('pick', [start, end]);
            ";
        }
        if ($search_date) {
            $new_arr = [];
            foreach ($search_date as $title => $k) {
                if ($output[$k]) {
                    $new_arr[$k] = $output[$k];
                } elseif ($output[$title]) {
                    $new_arr[$k] = $output[$title];
                }
            }
            $output = $new_arr;
        }
        $js = [];
        foreach ($output as $k => $v) {
            $js[] = [
                'text' => $k,
                "js:onClick(picker){
                    " . $v . "
                }",
            ];
        }
        $str = ['shortcuts' => $js];
        if ($start_date) {
            $disable_str = "
            return ctime < " . strtotime($start_date) . ";
            ";
            $str[] = "js:disabledDate(time){ 
                      let ctime = time.getTime()/1000;
                    " . $disable_str .
                "}";
        }
        return $str;
    }
    /**
     * 生成导入数据按钮
     */
    public function getImport($opt = [])
    {
        $title = $opt['title'] ?: lang('导入数据');
        $success = $opt['success'] ?: 'import_xls_uploaded';
        $save_url = $opt['save_url'];
        $js = $opt['js'];
        $table_body = $opt['table_body'];
        $dialog = $opt['dialog'] ?: "width='80%' top='20px' ";
        $is_pop = $success . "_pop";
        $pop_list = $success . "_list";
        $save_pop = $success . "_save_pop";
        $url = $opt['upload_url'] ?: $this->upload_url;
        $parse_url = $opt['parse_url'];
        $type = $opt['type'] ?: 'primary';
        $local_url = $opt['local_url'] ?: 'url';
        $html = "<el-upload  accept='.xls, .xlsx' :on-success='" . $success . "' action='" . $url . "' ><el-button type='" . $type . "' >" .
            ($opt['label'] ?: lang('导入'))
            . "</el-button></el-upload>";
        $this->method($success . "(res,file,list)", "
            let furl = res." . $local_url . "; 
            $.post('" . $parse_url . "',{url:furl},function(res){
                if(res.code == 0){
                    app." . $is_pop . " = true; 
                    app." . $pop_list . " = res.data;
                }else{
                    app." . $pop_list . " = [];
                    " . vue_message() . "
                }
            },'json');
        ");
        $this->data($pop_list, "[]");
        $this->data($is_pop, false);
        $this->data("import_pop_table_height", "");
        $this->created(['load_import_pop_table_height()']);
        $this->method("load_import_pop_table_height()", "
            this.import_pop_table_height = window.innerHeight - 230;
        ");
        $pop_html = '
            <el-dialog :close-on-click-modal="false"    ' . $dialog . '
              title="' . $title . '"
              :visible.sync="' . $is_pop . '"  >
              <el-table  :height="import_pop_table_height"
                :data="' . $pop_list . '"
                border
                style="width: 100%">
                ' . $table_body . '
              </el-table>              
              <span slot="footer" class="dialog-footer">
                <el-button @click="' . $is_pop . ' = false">取 消</el-button>
                <el-button type="primary" @click="' . $save_pop . '">确 定</el-button>
              </span>
            </el-dialog>
        ';
        $this->method($save_pop . "()", " 
            $.post('" . $save_url . "',{data:this." . $pop_list . "},function(res){
                " . vue_message() . "
                app." . $is_pop . " = false; 
                app." . $pop_list . " = [];
                " . $js . "
            },'json');
        ");
        return ['html' => $html, 'pop_html' => $pop_html];
    }
}
/**
 * 季度
 * 返回 k=>{0:开始 1:结束 flag:}
 */
function vue_get_jidu($time = '')
{
    $time = $time ?: now();
    if (strpos($time, ':') !== false) {
        $time = strtotime($time);
    }
    $i    = ceil(date("n", $time) / 3);
    $arr  = [1 => lang('一'), 2 => lang('二'), 3 => lang('三'), 4 => lang('四')];
    $year = date("Y", $time);
    $arr_1 = vue_get_jidu_array($year);
    $new_arr = [];
    $flag = true;
    $ex = true;
    $j = 1;
    foreach ($arr as $k => $v) {
        $vv = $arr_1[$k];
        $vv['flag'] = false;
        if ($j <= $i) {
            $vv['flag'] = true;
        }
        $new_arr["第" . $v . "季度"] = $vv;
        $j++;
    }
    return $new_arr;
}
/**
 * 每个季度开始、结束时间
 */
function vue_get_jidu_array($year)
{
    return [
        1 => [$year . "-01-01", $year . "-03-" . date("t", strtotime(($year . "-03")))],
        2 => [$year . "-04-01", $year . "-06-" . date("t", strtotime(($year . "-06")))],
        3 => [$year . "-07-01", $year . "-09-" . date("t", strtotime(($year . "-09")))],
        4 => [$year . "-10-01", $year . "-12-" . date("t", strtotime(($year . "-12")))],
    ];
}
/**
 * vue message
 */
function vue_message($time = 3)
{
    $time = 1000 * $time;
    return "  
    if(!app._vue_message){
        app._vue_message = true;
        app.\$message({duration:" . $time . ",type:res.type,message:res.msg||res.message,onClose:function(){
            app._vue_message = false;
        }});        
    }
    \n";
}
/**
 * loading效果
 */
function vue_loading($name = 'load', $txt = '加载中')
{
    return "const " . $name . " = app.\$loading({
          lock: true,
          text: '" . $txt . "',
          spinner: 'el-icon-loading',
          background: 'rgba(0, 0, 0, 0.7)'
    }); \n";
}
/**
 * <div class="table-drop"><el-table ></el-table></div>
 *
 *  vue_el_table_drag($ele='.table-drop',$data='list',$js = "
 *   alert(1);
 * ");
 * 表格拖拽
 */
function vue_el_table_drag($ele = '.table', $data = 'form.video_list', $end = '')
{
    $js = "
    setTimeout(function(){
        const tbody = document.querySelector('" . $ele . " .el-table__body-wrapper tbody');   
        Sortable.create(tbody, { 
            draggable: '" . $ele . " .el-table__row',
            handle: '.drag', 
            onEnd(evt) {   
                let list = app." . $data . ";
                if (evt.oldIndex !== evt.newIndex) {
                    let newIndex = evt.newIndex;
                    let oldIndex = evt.oldIndex;   
                    
                    let old = list[oldIndex];  
                    list.splice(oldIndex,1);
                    list.splice(newIndex,0,old);  
                    app." . $data . " = [];   
                    app.\$nextTick(()=>{
                        app." . $data . " = list; 
                        " . $end . "
                    }) ;
                    app.\$forceUpdate();
                }
            } 
        });
    },50)";
    global $vue;
    $vue->created(['load_vue_drag()']);
    $vue->method('load_vue_drag()', $js);
}
