<!DOCTYPE html>
<html lang="ja">
	<head>
	<script src="//ajax.googleapis.com/ajax/libs/jquery/1.10.2/jquery.min.js"></script>
	<script>
		var target;
		window.addEventListener("message", receive_x, false);

		$(function(){
			if ( parent.postMessage ) {
				target = parent;
			} else {
				if (parent.document.postMessage) {
					target = parent.document;
				} else {
					target = undefined;
				}
			}
			if (typeof target != "undefined") {
				target.postMessage('start','*');
			}
		});

		function ajax_send( data, url ){
			$.ajax({
				type: "POST"
				, url: url
				, data: data
				, dataType:'json'
				, success: function(data){
					target.postMessage( JSON.stringify(data), '*' );
				}
				, error: function(data,status){
					data['cmd']='error';
					target.postMessage( JSON.stringify(data), '*' );
				}
			});
		}

		function receive_x( d ) {
			ajax_data = JSON.parse( d.data );
			ajax_send( ajax_data.data, ajax_data.url );
		}

	</script>
	</head>
	<body style='display:none'>
	</body>
</html>

