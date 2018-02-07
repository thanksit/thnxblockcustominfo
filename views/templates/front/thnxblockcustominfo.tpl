{if $thnxinfos|@count > 0}
	<div id="custom_info_block" class="custom_info_block">
		<div class="row width{$thnxinfos|@count}">
			{foreach from=$thnxinfos item=info}
				<div class="col-xs-12">
					<div class="single_info_block">
						{$info.text}
					</div>
				</div>
			{/foreach}
		</div>
	</div>
{/if}