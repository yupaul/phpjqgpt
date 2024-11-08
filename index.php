<?php

if(!empty($_REQUEST['reset'])) {
	session_start();
	session_unset();
	session_destroy();
	header('Location: ' . $_SERVER['SCRIPT_URL']);
	die();
}

require __DIR__ . '/inc/cfg.php';
require __DIR__ . '/inc/functions.php';

ini_set('display_startup_errors', CHT_DEBUG ? 1 : 0);
ini_set('display_errors', CHT_DEBUG ? 1 : 0);
error_reporting(CHT_DEBUG ? E_ALL : 0);


session_start();
if(!empty($_REQUEST['pwd'])) $_SESSION['cht_pwd'] = $_REQUEST['pwd'];

$RESPONSE = '';

$sliders = [
	'top_p' => [
		'range' => '0,1',
		'step'=> '0.001',
		'precision' => 3,
	],
	'temperature' => [
		'range' => '0,2',
		'step'=> '0.001',		
		'precision' => 3,
	],
	'max_tokens' => [
		'range' => '1,2048',
		'step' => '1',
		'precision' => 0,
	],	
];

if(!empty($_REQUEST['save_settings']) && !empty($_SESSION['cht_pwd'])) {
	$changed = false;
	foreach($_REQUEST as $k => $v) {
		if(!isset($CHT_CFG[$k]) || strpos($k, '_') === 0) continue;
		$v = validate($k, $v);
		if($v === false) continue;
		if(!$changed) $changed = true;
		$CHT_CFG[$k] = $v;
	}
	if($changed) file_put_contents(__DIR__ . '/inc/cfgw.php', '<' . '?' . 'p' . 'hp' . "\n\n" . '$CHT_CFG = ' . var_export($CHT_CFG, true) . ";\n\n");		

	echo json_encode($CHT_CFG);
	die();
}

if(!empty($_REQUEST['q']) && !empty($_SESSION['cht_pwd'])) {
	$params = [];
	foreach(['system_role', 'model'] as $k) {
		$params[$k] = !empty($_REQUEST[$k]) ? $_REQUEST[$k] : $CHT_CFG[$k];
	}

	$data = [
		'model' => $params['model'],
		'n' => $CHT_CFG['_n'],
		'messages' => [
			[
				'role' => 'system',
				'content' => $params['system_role'],
			]   
		]
	];
	foreach(['max_tokens', 'temperature', 'top_p'] as $k) {
		$v = false;
		if(isset($_REQUEST[$k])) $v = validate($k, $_REQUEST[$k]);					
		$data[$k] = $v !== false ? $v : $CHT_CFG[$k];
		$params[$k] = $data[$k];
	}
	
	$response_only = isset($_REQUEST['r']) && !$_REQUEST['r'];
	
	if(!$response_only) {
		if(!isset($_SESSION['qa']) || empty($_REQUEST['_cc'])) $_SESSION['qa'] = [];	

		foreach($_SESSION['qa'] as $_qa) {			
			if(empty($_qa['q']) || empty($_qa['a'])) continue; 
			$data['messages'][] = [
				'role' => 'user',
				'content' => $_qa['q'],
			];
			$data['messages'][] = [
				'role' => 'assistant',
				'content' => $_qa['a'],
			];						
		}
	}
	
	$data['messages'][] = [
		'role' => 'user',
		'content' => trim($_REQUEST['q']),
	];	

	$RESPONSE = call_api($data, $_SESSION['cht_pwd']);
	
	if(!$response_only)  {
		if(!empty($RESPONSE['choices'][0]['message']['content'])) {
			$_SESSION['qa'][] = [
				'q' => trim($_REQUEST['q']),
				'a' => $RESPONSE['choices'][0]['message']['content'], 
				'metadata' => $RESPONSE
			];
		} else {
			echo htmlspecialchars(json_encode($RESPONSE), ENT_QUOTES, 'UTF-8');
		}
	} else {
		header('Content-Type: text/plain');
		if(is_array($RESPONSE)) {
			if(!empty($RESPONSE['choices'][0]['message']['content'])) {
				echo $RESPONSE['choices'][0]['message']['content'];
			} else {
				echo json_encode($RESPONSE);
			}
		} else {
			echo $RESPONSE;
		}
		die();
	}
} else {
	$params = $CHT_CFG;
}
$QA = isset($_SESSION['qa']) ? array_reverse($_SESSION['qa']) : [];
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Chat</title>
<script src="//code.jquery.com/jquery-3.6.3.min.js" crossorigin="anonymous"></script>
<link rel="stylesheet" type="text/css" href="//cdnjs.cloudflare.com/ajax/libs/mini.css/3.0.1/mini-default.min.css" media="screen" />
<!-- <script src="assets/simple-slider.min.js"></script> -->
<link rel="stylesheet" href="assets/simple-slider.css" type="text/css" />
<script src="//cdnjs.cloudflare.com/ajax/libs/json2html/2.2.1/json2html.min.js"></script>
<script src="assets/visualizer.js"></script>
<link rel="stylesheet" href="assets/visualizer.css" type="text/css" />
<style>

#qform {
	z-index: 100000;
	position: sticky;
	top: 0;
}
.qadiv {
	margin: 6px;
	padding: 6px;
	border: 1px solid #aba;\
	border-radius: 8px;
}
</style>
</head>
<body>
<form id="qform">
	<input type="text" name="q" placeholder="Type prompt here" value="" style="width:80%" /> <button type="button" class="primary qform_submit">Submit</button>
	<?php if (sizeof($QA)) { ?>
	<div><input type="checkbox" value="1" name="_cc"<?php if (!empty($_REQUEST['_cc'])) { ?> checked="checked"<?php } ?> /> Continue conversation</div>
	<?php } ?>
	<div>
		<a href="javascript:;" onclick="javascript:$('#settings_div').toggle();">settings</a>
		<div style="display:none" id="settings_div"><fieldset><div class="row"></div></fieldset>
		<button type="button" class="tertiary save_settings_button">Save Default Settings</button>
		<span class="saving_settings_span" style="display:none;color:blue"><small><i>saving &hellip;</i></small></span>
		<a href="./?reset=1">Reset All</a>
		</div>
		
	</div>
</form>

<?php foreach ($QA as $i => $qa) { ?>
<div class="qadiv" id="qadiv_<?php echo $i; ?>">
<h3>Q: <?php echo hs($qa['q']); ?></h3>
<p><b>A:</b> <?php echo nl2br(hs($qa['a'])); ?></p>
<a href="javascript:;" class="metadata_link" rel="<?php echo $i; ?>">metadata</a>
<div class="metadata_div" style="display:none" data-processed="0"></div>
</div>
<?php } ?>

<script>
$(document).ready(function() {
	const sliders = <?php echo json_encode($sliders); ?>;
	
	let default_settings = <?php echo json_encode($CHT_CFG); ?>;
	let params = <?php echo json_encode($params); ?>;
	let metadata = <?php echo json_encode(array_map(function($_qa) {return $_qa['metadata']; }, $QA)); ?>	

	const ucwords = (s) => {
		const ar = s.toLowerCase().split('_');
		return ar.map((_s) => _s.substring(0, 1).toUpperCase()+_s.substring(1)).join(' '); 
	}
	
	let _pwd = localStorage.getItem('cht_pwd')
	if(_pwd) {
		$('#qform').prepend('<input type="hidden" name="pwd" value="'+_pwd+'" />');
	} else {
<?php if (!empty($_SESSION['cht_pwd'])) { ?>
		localStorage.setItem('cht_pwd', '<?php echo $_SESSION['cht_pwd']; ?>')
		$('#qform').prepend('<input type="hidden" name="pwd" value="'+_pwd+'" />');
<?php } else { ?>
		$('#qform').prepend('<input type="password" name="pwd" size="100" placeholder="password" />');
<?php } ?>
	}
	
	$(document).on('click', '.qform_submit', function() {
		$('#qform').submit();
	});
	
	$(document).on('click', '.metadata_link', function() {
		const i = parseInt($(this).attr('rel'));
		let mdiv = $('#qadiv_'+i).find('.metadata_div');
		if(mdiv.attr('data-processed') === '0' && Array.isArray(metadata) && metadata.length > i) {
			mdiv.attr('data-processed', '1');			
			var _visualizer = new visualizer(mdiv);    	
			_visualizer.visualize(metadata[i]);			
		}
		mdiv.toggle();
	});
	
	$(document).on('click', '.save_settings_button', function() {
		let _this = $(this);
		_this.hide();
		$('.saving_settings_span').show();
		let _data = {
			pwd: $('[name="pwd"]').val(),
			save_settings: 1,
		};
		for (let _param in params) {
			if(_param.indexOf('_') === 0 || !$('[name="'+_param+'"]').length) continue;
			if(!$('[name="'+_param+'"]').val().length) return false;
			_data[_param] = $('[name="'+_param+'"]').val();
		}
		
		$.ajax({
			url: 'index.php',
			dataType: 'json',
			type: 'post',
			data: _data,
			success: function(j) {
				if(j) {
					params = j;
					default_settings = JSON.parse(JSON.stringify(j));
				}
				$('.saving_settings_span').hide();		
				_this.show();
			},			
		})
	});
	
	$('#qform').on('submit', function() {		
		for(let k in default_settings) {
			if($('[name="'+k+'"]').length && $('[name="'+k+'"]').val() == default_settings[k]) $('[name="'+k+'"]').remove();
		}
		return true;
	});
	
	for (let _param in params) {
		if(_param.indexOf('_') === 0) continue;
		let _h = `<div class="col-sm-12 col-md-6">
		<label for="${ _param }">${ ucwords(_param) }</label>
		<input type="text" name="${ _param }" placeholder="${ ucwords(_param) }" ${
			sliders.hasOwnProperty(_param) ? 'data-slider="true" data-slider-range="'+sliders[_param].range+'" data-precision="'+sliders[_param].precision+'" data-slider-step="'+sliders[_param].step+'"' : '' } /></div>`;
		$('#settings_div .row').append(_h);
		$('[name="'+_param+'"]').val(params[_param]);	
	}	
	
	var slscript = window.document.createElement('script');
	slscript.src = 'assets/simple-slider.min.js';
	
	slscript.onload = () => {
		$("[data-slider]")
		.each(function () {
		var input = $(this);
		$("<span>")
			.addClass("output")
			.insertAfter($(this));
		}).bind("slider:ready slider:changed", function (event, data) {
		$(this)
        .nextAll(".output:first")
          .html(data.value.toFixed(parseInt($(this).attr('data-precision'))));
		});
	}
	window.document.getElementsByTagName('head')[0].appendChild(slscript);
});
</script>
</body>
</html>