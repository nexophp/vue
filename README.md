# vue 

## 初始化

vue 3

~~~
$vue =  new Vue;
$vue->version = 3;
~~~

vue 2
~~~
$vue =  new Vue; 
~~~

### index

~~~
<el-table-column type="index" label="序号" :index="indexMethod" width="80">
</el-table-column>
~~~

### 时间区间

~~~
$vue->search_date = [
  '今天',
  '昨天',
  '本周',
  '上周',
  '上上周',
  '本月',
  '上月',
  '上上月',
  '本年'=>'今年', 
  '上年'=>'去年',
  '上上年',
  '最近一个月',
  '最近两个月',
  
  '最近三个月',
  '第一季度', 
  '第二季度', 
  '第三季度', 
  '第四季度', 
];
//限制在这个时间之前的无法选择
$vue->start_date = '2023-11-01';

$vue->add_date();

~~~

时间定时刷新

~~~
$vue->loop_picker_options('/product_quality/api_index/date',3);
~~~

接口返回

~~~
return json_success(['data'=>(new \Vue)->get_date_area()]); 
~~~




`search_date` 以 `key`=>`value`形式存在，`key`是显示的时间，`value`是显示的标题


~~~
<el-date-picker   v-model="where.date" value-format="yyyy-MM-dd" :picker-options="pickerOptions" size="medium" type="daterange" range-separator="至" start-placeholder="开始日期" end-placeholder="结束日期">
</el-date-picker>
~~~

![演示时间效果](/tests/date1.png "演示时间效果") 

### data 
~~~
$vue->data('text','welcome');
~~~

### created

~~~ 
$vue->created(['load()']);
$vue->method('load()',"

");

~~~

### mounted 
~~~
$vue->mounted("a","
  alert(2);
")
~~~
其中`a`是`key`

### watch

~~~
$vue->watch("page(new_val,old_val)","
  console.log('watch');
  console.log(old_val);
  console.log(new_val);
")
~~~

~~~ 
$vue->watch("where.per_page","
  handler(new_val,old_val){
    console.log('watch');
    console.log(old_val);
    console.log(new_val);
  },  
"); 
~~~

~~~
$vue->watch("where","
  handler(new_val,old_val){
    console.log('watch');
    console.log(old_val.per_page);
    console.log(new_val.per_page);
  }, 
  deep: true
");
~~~


底部加入
~~~
<?php  
if($vue){
?>
<script type="text/javascript">
	<?=$vue->run();?>
</script>
<?php }?> 
~~~

# wangeditor 富文本

如`body`字段

在html中
~~~
<?=$vue->editor()?>
~~~

vue代码

~~~
$vue->editor_method();
$vue->method("edit_form(row)","
    let f = this.field;
    this.form = {};
    for(let r of this.field){
        this.\$set(this.form,r,row[r]);
    } 
    this.is_open = true; 
".$vue->load_editor_edit());
~~~

# 压缩JS
安装 

~~~
yarn add --dev javascript-obfuscator
~~~

配置
~~~
$config['vue_encodejs'] = true;
$config['vue_encodejs_ignore'] = ['/plugins/config/config.php'];
~~~

 

### 一般函数

每个季度开始、结束时间
~~~
vue_get_jidu_array($year)
~~~

某月的最后一天
~~~
vue_get_last_day($month = '2023-07')
~~~

### wangeditor 5 

有时需要替换原来的图片上传按钮，以下为演示，实际使用请根据情况处理。
~~~
$vue->data('is_open_editor',false);
$vue->editor_image_upload_click = "
    app.add_media('editorbody');
    app.is_open_editor = true;
"; 
~~~

### 导入文件

~~~
$import = $vue->get_import([
    'js'=>" alert('导入成功');";
    'upload_url'=>'/sys/upload/one',
    'parse_url'=>'/product_quality/goods/import_parse',
    'save_url'=>'/product_quality/goods/import_parse_save',
    'label'=>'导入xls',
    'table_body'=>'
    <el-table-column   prop="desc"  label="产品名称" width=""></el-table-column>
    <el-table-column   prop="reg_num"  label="状态" width="">
         <template slot-scope="scope">
            <span v-if="scope.row.is_err">
              <div v-html="scope.row.err" style="color:red;font-size:12px;"></div>
            </span>
            <span v-else style="color:green;font-size:12px;">
                可导入
            </span>
         </template>
    </el-table-column>
    '
]); 
~~~

返回 `html` `pop_html`

~~~
<?php 
$html = $import['html'];
?>
<?=$import['pop_html']?>
~~~


接口处理
import_parse
~~~
  $url = $this->input['url'];
  $file = PATH.$url;
  if(!file_exists($file)){
    return json_error(['msg'=>'操作异常']);
  }
  $all = \helper_v3\Xls::load($file,[ 
        '产品名称'   => 'desc',
        '规格型号'   => 'spec',
        '批号'       => 'product_ph',
        '生产日期'   => 'produce_date',
        '失效日期'   => 'invalid_date',
        '唯一码'     => 'uuid',
        '注册证号'     => 'reg_num',
  ]);
  foreach($all as $k=>$v){ 
  }
  if(!$all){
    return json_error(['msg'=>'导入的文件数据异常']);
  } 
  foreach($all as $k=>&$v){
  
  if($res){
    $err[] = "唯一码已存在";
  }
  $v['is_err'] = false;
  if($err){
    $v['err'] = implode("<br>",$err);
    $v['is_err'] = true;
  }
}
if($all){
  $all = array_values($all);
}
return json_success(['data'=>$all]); 
~~~

 