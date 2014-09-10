
(function( $ ) {

  $.widget( "yii.yiijob", {
 
    // These options will be used as defaults
    options: {
    	onComplete : null,
    	refreshInterval : 1000,
    	completeUrl: null
    },
   
    _id : null,
    floatingBarPercent : 0,
    floatingBarRunning : false,
    
    autoProgress : function()
    {
    	var progressBar = this.element.find("div.progress div.bar");
    	
		if (this.floatingBarPercent < 100)
			this.floatingBarPercent += 0.5;
		else
			this.floatingBarPercent = 0;
		
		progressBar.css("width", "20%"); 
		progressBar.css("margin-left", (this.floatingBarPercent - 20) + "%"); 
		
		var that = this;
		
		if (this.floatingBarRunning)
		{
			setTimeout(function() {
				that.autoProgress();
			}, 25);
		}
    },
    
    updateProgress : function()
    {
    	var progress = this.element.attr("data-progress");
    	var progressContainer = this.element.find("div.progress");
		var progressBar = this.element.find("div.progress div.bar");
		
		var statusId = this.element.attr("data-statusid");
		
		var that = this;
		
		if (statusId != 2)
		{
			this.floatingBarRunning = false;	
			this.floatingBarPercent = 0;
		}
    	
    	if (progress < 0)
    	{
    		if (statusId == 2)
    		{
	    		if (!this.floatingBarRunning)
	    		{
	    			progressContainer.removeClass('progress-striped');
	    			progressContainer.removeClass('active');
	    			this.floatingBarRunning = true;
					this.autoProgress();
	    		}
    		}
    	}
    	else
    	{
    		if (!progress)
    			progress = 0;
    		
    		progressBar.css("width", progress + "%");
    	}
    },
    
    update : function()
    {
    	var statusId = this.element.attr("data-statusid");
    	
    	var hasRunNow = this.element.find(".job-status-runnow").length > 0;
    	var hasStatusText = this.element.find(".job-status-text").length > 0;
    	var hasStatus = hasRunNow || hasStatusText;
    	
    	if (hasStatus)
    	{
	    	if (statusId == 2)
	    	{
	    		this.element.find(".job-status").hide();
	    		this.element.find(".job-progress").fadeIn();
	    	}
	    	else
	    	{
	    		this.element.find(".job-progress").hide();
	    		this.element.find(".job-status").fadeIn();
	    		
	    	}
    	}
    	else
    	{
    		this.element.find(".job-progress").show();
    	}
    	
		this.updateProgress();
    	
    	this.element.find('.job-status-text').html(this.element.attr('data-statusName'));
    	
    	if (hasRunNow && hasStatusText)
    	{
	    	if (statusId == 1)
			{
	    		this.element.find('.job-status-runnow').show();
	    		this.element.find('.job-status-text').hide();
			}
	    	else
	    	{
	    		this.element.find('.job-status-runnow').hide();
	    		this.element.find('.job-status-text').show();
	    	}
    	}
    	else if (hasStatusText)
    	{
    		this.element.find('.job-status-text').show();
    	}
    	else if (hasRunNow)
    	{
	    	if (statusId == 1)
			{
	    		this.element.find('.job-status-runnow').show();
			}    		
    	}
    },
    
    refresh: function()
    {
    	var that = this;
    	var href = this.element.attr('data-href');
    	var jobToken = this.element.attr('data-jobtoken');
    	var refresh = this.element.attr('data-refresh');
    	
    	if (href && jobToken && refresh)
    	{
    		$.ajax( {
    			url : href,
    			type : 'GET',
    			async: true,
    			cache : false,
    			data : {token:jobToken},
    			dataType:'json',
    			success : function(data) {
    				var refresh = false;
    				
    				if ("progress" in data && $('#' + that._id).length)
    				{
    					that.element.attr('data-progress', data.progress);
    					that.element.attr('data-statusId', data.statusId);
    					that.element.attr('data-statusName', data.statusName);
    					
    					// Update progress first
    					that.update();
    					
    					// Only refresh, if status Waiting or Running and element still exists
    					if (data.statusId <= 2)
    						refresh = true;
    					
    					// If job is completed (successfully or terminated) execute function if exists
    					if (data.statusId > 2)
    					{    		
    						if (that.options.completeUrl) {
    			        		$.ajax( {
    			        			url : that.options.completeUrl,
    			        			type : 'GET',
    			        			async: true,
    			        			cache : false,
    			        			data : {token:jobToken},
    			        			dataType:'html',
    			        			success : function(html) {
    			        				$(that.element).html(html);
    			        			}
    			        		});
    						}
    						
    			    		if (that.options.onComplete && typeof that.options.onComplete === 'function')
    			    		{
    			    			that.options.onComplete(data.statusId, data.result);
    			    		}
    					}
    				}
    				
    				if (refresh)
    				{
	    				setTimeout(function() {
	    					that.refresh();
	    				}, that.options.refreshInterval);
    				}
    				
    			}
    		});    		
    	}    	
    },
    
    
 
    // Set up the widget
    _create: function() {
    	this._id = this.element.attr('id');
    	this.update();
    	this.refresh();
    	
    	var that = this;
    	
    	$(document).off('click.runnow');
    	$(document).on('click.runnow', '.job-status-runnow', function(event)
    	{
    		event.preventDefault();
			if ($(this).hasClass("disabled"))
				return;
			
    		$(this).button('loading');
    		
    		var that = this;
    		
    		var href = $(this).attr('data-href');
    		var jobToken = $(this).closest('.job-info').attr('data-jobtoken');
    		
        	if (href)
        	{
        		$.ajax( {
        			url : href,
        			type : 'GET',
        			async: true,
        			cache : false,
        			data : {token:jobToken},
        			dataType:'json',
        			success : function(data) {
        			}
        		});
        	}
    		
    	});
    }, 
    
    // Use the destroy method to clean up any modifications your widget has made to the DOM
    _destroy: function() {

    }
  });
}( jQuery ) );

