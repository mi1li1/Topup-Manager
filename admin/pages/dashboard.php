<?php

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap">

<h1>🎮 Topup Manager</h1>

<hr>

<h2>插件状态</h2>

<table class="widefat striped" style="max-width:700px">

<tr>
<td width="220"><strong>插件版本</strong></td>
<td>1.0.0</td>
</tr>

<tr>
<td><strong>WooCommerce</strong></td>

<td>

<?php

if(class_exists('WooCommerce')){

    echo '<span style="color:green">✔ 已安装</span>';

}else{

    echo '<span style="color:red">✘ 未安装</span>';

}

?>

</td>

</tr>

<tr>

<td><strong>游戏充值字段</strong></td>

<td style="color:green">

✔ 已启用

</td>

</tr>

<tr>

<td><strong>Fazer 商品字段</strong></td>

<td style="color:green">

✔ 已启用

</td>

</tr>

</table>

</div>



