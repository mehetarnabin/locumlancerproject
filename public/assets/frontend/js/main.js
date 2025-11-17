document.addEventListener("DOMContentLoaded", function () {

  userDropdown();
  dropdown();
  projectTab();
  headersticky();
  accordion();
  formSwitcher();
  processSlider();
  advancedFilterform();
  filterButtonActive();
  filterbar();
  passwordViewer();
  jobheadersticky();
  showjobSidebar();
});
showjobSidebar =()=> {
  $('.sidebar-btn').on('click', function() {
    $(this).parents('.results-container').toggleClass('show-job-sidebar');
  })
}
userDropdown = ()=> {
  // Dashboard Button Handler - Only navigates, no dropdown
  $('.dashboard-icon').on('click', function(e) {
    // Close any open dropdown if exists
    $('.main-header').removeClass('dropdown-show');
    // Allow navigation to proceed normally - don't prevent default or stop propagation
    // The href will handle navigation
  });
  
  // Avatar Button Handler - For non-home pages (shows dropdown)
  $('.avatar-button').not('.home-avatar-btn').on('click', function(e) {
    // Don't toggle if clicking on a link inside the dropdown
    if ($(e.target).closest('.user-dropdown a').length) {
      return; // Let the link navigate normally
    }
    
    e.stopPropagation();
    e.preventDefault();
    // Toggle dropdown for avatar button on non-home pages
    $('.main-header').toggleClass('dropdown-show');
  });
  
  // Home Avatar Button Handler - Currently no dropdown (removed per user request)
  // This is prepared for future dropdown implementation
  $('.home-avatar-btn').on('click', function(e) {
    // Currently does nothing - dropdown removed from home page
    // When dropdown is re-implemented, uncomment below:
    /*
    if ($(e.target).closest('.user-dropdown a, .home-user-dropdown a').length) {
      return;
    }
    e.stopPropagation();
    e.preventDefault();
    $('.main-header').toggleClass('dropdown-show');
    */
  });
  
  // Close dropdown when clicking outside
  $(document).on('click', function (e) {
    // Close if clicking on dashboard button or its container
    if ($(e.target).closest('.dashboard-icon, .dashboard-icon-container').length) {
      $('.main-header').removeClass('dropdown-show');
      return;
    }
    // Don't close if clicking on the avatar button or dropdown itself
    if (!$(e.target).closest('.avatar-button, .user-dropdown, .home-user-dropdown').length) {
      $('.main-header').removeClass('dropdown-show');
    }
  });
  
  // Prevent dropdown from closing when clicking inside it (but allow links to work)
  $('.user-dropdown, .home-user-dropdown').on('click', function(e) {
    // Only stop propagation if not clicking on a link
    if (!$(e.target).is('a')) {
      e.stopPropagation();
    }
  });
  
}
jobheadersticky = () => {
     var $jobDetail = $('.job-title-wrap');
    var $jobHeader = $('.job-header');
    
    if ($jobDetail.length && $jobHeader.length) {
        var jobHeaderOffset = $jobHeader.offset().top - $jobDetail.offset().top;
        
        $jobDetail.on('scroll', function () {
            var scrollTop = $(this).scrollTop();
            if (scrollTop > jobHeaderOffset) {
                $('body').addClass('job-scrolled');
            } else {
                $('body').removeClass('job-scrolled');
            }
        });
    } 


    
  
    // var iScrollPos = 0;
    // $jobDetail.on('scroll', function () {
    //   var iCurScrollPos = $(this).scrollTop();
  
    //   if (iCurScrollPos > iScrollPos) {
    //     $('.job-scrolled .job-header').css('top', '-75px');
    //   } else {
    //     $('.job-scrolled .job-header').css('top', '0');
    //   }
  
    //   iScrollPos = iCurScrollPos;
    // });


    $('.share-btn').on('click', function (e) {
      e.stopPropagation();
      $(this).find('.share-dropdown').toggleClass('active');
    });

    setTimeout(function () {
      $('.st-btn[data-network="facebook"] .st-label').text('Facebook');
      $('.st-btn[data-network="twitter"] .st-label').text('Twitter');
      $('.st-btn[data-network="linkedin"] .st-label').text('LinkedIn');
      $('.st-btn[data-network="email"] .st-label').text('Email');
      $('.st-btn[data-network="sharethis"] .st-label').text('More');
    }, 1000);

    $(document).on('click', function () {
      $('.share-dropdown').removeClass('active');
    });

    $(document).on('click', '.share-btn', function (event) {
      event.stopPropagation();
    });
};
 passwordViewer = () => {
  $('.password-viewer').on('click', function () {
    const input = $(this).siblings('input'); 
    const icon = $(this).find('i'); 
    if (input.attr('type') === 'password') {
        input.attr('type', 'text');
        icon.removeClass('fa-eye').addClass('fa-eye-slash');
    } else {
        input.attr('type', 'password');
        icon.removeClass('fa-eye-slash').addClass('fa-eye');
    }
  });
};


const accordion = () => {
  $(".accordion-item").each(function () {
    const $accordionItem = $(this);

    $accordionItem.find(".accordion-body").hide();

    $accordionItem.find(".accordion-header").on("click", function () {
      const $currentBody = $(this).next(".accordion-body");

      if ($currentBody.is(":visible")) {
        $currentBody.slideUp(300);
        $(this).removeClass("active");
      } else {
        $(".accordion-body").slideUp(300);
        $(".accordion-header").removeClass("active");

        $currentBody.slideDown(300);
        $(this).addClass("active");
      }
    });
  });
};

headersticky = () => {
  $(window).scroll(function () {
    var sticky = $("body"),
      scroll = $(window).scrollTop();
    if (scroll >= 100) sticky.addClass("header-fixed");
    else sticky.removeClass("header-fixed");
  });
  $('button.th-btn').on('click', function () {
    $(this).parent('.dropdown').toggleClass('dropdownActive');
  })
};


dropdown = () => {
  $(
    ".main-header .main-header__wrapper .main-header__nav > ul > li, #sidebar > ul > li"
  )
    .has("ul")
    .append('<span  class="fa fa-chevron-down"></span>');

  $(" #sidebar > ul > li > span").on("click", function () {
    $(this).parent("li").find("ul").slideToggle(400);
  });

  $(".hamburger").on("click", function () {
    $("body").addClass("show__side--menu").css({ overflow: "hidden" });
    $("body").children().not("#sidebar").addClass("blur");
    $(".overlay").fadeIn(400);
  });

  $(".overlay").on("click", function () {
    $("body").removeClass("show__side--menu").css({ overflow: "auto" });
    $(this).fadeOut(400);
  });
};

projectTab = () => {
  $(".el-tab-content").hide();
  $(".el-tab-content.active").show();
  $(".nav-item").click(function () {
    var tabId = $(this).data("id");
    $(this).siblings().removeClass("active");
    $(this).addClass("active");
    $(".el-tab-content.active").fadeOut(200, function () {
      $(this).removeClass("active");
      $("#" + tabId)
        .fadeIn(200)
        .addClass("active");
    });
  });
};


formSwitcher = () => {
  $('input[name="form-switch"]').on('change', function () {
    const selectedForm = $(this).val();
    $('.form-container-wrap').removeClass('active');
    $('#' + selectedForm).addClass('active');
  });
};



processSlider = () => {
  var $carousel = $('.process-items-carousel');

  $carousel.owlCarousel({
    items: 3,
    loop: true,
    margin: 0,
    nav: true,
    autoplay: false,
    autoplayTimeout: 8000,
    dots: true,
    dotsData: true,
    navText: [
      '<i class="fa fa-chevron-left"></i>',
      '<i class="fa fa-chevron-right"></i>'
    ],
    responsive: {
      0: { items: 1.2 },
      600: { items: 2 },
      1000: { items: 3 }
    },
    onInitialized: (event) => {
      updateCustomActive(event);
      scrollActiveDotIntoView(event);
    },
    onChanged: (event) => {
      updateCustomActive(event);
      scrollActiveDotIntoView(event);
    }
  });

  function updateCustomActive(event) {
    var $carouselElem = $('.process-items-carousel');
    $carouselElem.find('.owl-item').removeClass('custom-active');

    var clones = event.relatedTarget._clones.length / 2;
    var slideCount = event.item.count;

    var realIndex = event.item.index - clones;
    if (realIndex < 0) realIndex = slideCount + realIndex;
    if (realIndex >= slideCount) realIndex = realIndex % slideCount;

    var $allItems = $carouselElem.find('.owl-item:not(.cloned)');
    var $targetItem = $allItems.eq(realIndex);
    $targetItem.addClass('custom-active');
  }

  // Scroll the active dot so it stays visible (simulate looping)
  function scrollActiveDotIntoView(event) {
    setTimeout(() => {
      const $activeDot = $('.section-process .owl-dot.active')[0];
      if ($activeDot) {
        $activeDot.scrollIntoView({
          behavior: 'smooth',
          inline: 'center',
          block: 'nearest'
        });
      }
    }, 100);
  }
}

advancedFilterform = () => {
  $('.advanced-filter-link').on('click', function () {
    $(this).parent().toggleClass('active');
  });
  $(document).on('click', function (event) {
    if (!$(event.target).closest('.opt-field').length) {
      $('.opt-field').removeClass('active');
    }
  });
  $('.advanced-filter-field > div > label').on('click', function (event) {
    event.stopPropagation();
    $('.opt-field').not($(this).parent()).removeClass('active');
    $(this).parent().toggleClass('active');
  });
}
 

filterbar = () => {
    // Toggle dropdown on label click
    $(document).on('click', '.row-group-search .opt-field-label, .advanced-filter-field .opt-field-label', function (event) {
        event.stopPropagation();
        const parentField = $(this).parent();
        $('.opt-field').not(parentField).removeClass('active');
        parentField.toggleClass('active');
        console.log('Advanced filter label clicked');
    });

    // Prevent dropdown closing when clicking inside
    $('.opt-field-dropdown').on('click', function (event) {
        event.stopPropagation();
    });

    // Handle checkbox changes
    $('.opt-field-dropdown input[type="checkbox"]').on('change', function (event) {
        event.stopPropagation();
        const checkbox = $(this);
        const optField = checkbox.closest('.opt-field');
        const mainLabel = optField.find('.opt-field-label span');
        const isMultiple = optField.hasClass('multiple-select');

        const defaultText = mainLabel.data('default') || mainLabel.text().trim();
        mainLabel.data('default', defaultText);

        if (isMultiple) {
            // Multiple select: show all selected
            const selectedValues = optField.find('input[type="checkbox"]:checked')
                .map(function () {
                    return $(this).parent().text().trim();
                }).get();

            mainLabel.html(selectedValues.length > 0 ? selectedValues.join(', ') : defaultText);
        } else {
            // Single select: only one can be selected
            if (checkbox.is(':checked')) {
                mainLabel.html(checkbox.parent().text().trim());
                // Uncheck others
                optField.find('input[type="checkbox"]').not(checkbox).prop('checked', false);
            } else {
                mainLabel.html(defaultText);
            }
            // Close dropdown after selection
            optField.removeClass('active');
        }
    });

    // Close dropdown if clicking outside
    $(document).on('click', function () {
        $('.opt-field').removeClass('active');
    });

    // --- Initialization on page load ---
    $('.opt-field').each(function () {
        const optField = $(this);
        const mainLabel = optField.find('.opt-field-label span');
        const isMultiple = optField.hasClass('multiple-select');

        // Save default text if not saved yet
        const defaultText = mainLabel.data('default') || mainLabel.text().trim();
        mainLabel.data('default', defaultText);

        if (isMultiple) {
            const selectedValues = optField.find('input[type="checkbox"]:checked')
                .map(function () {
                    return $(this).parent().text().trim();
                }).get();
            mainLabel.html(selectedValues.length > 0 ? selectedValues.join(', ') : defaultText);
        } else {
            const selectedCheckbox = optField.find('input[type="checkbox"]:checked').first();
            const selectedText = selectedCheckbox.length ? selectedCheckbox.parent().text().trim() : defaultText;
            mainLabel.html(selectedText);
        }
    });
};

const filterButtonActive = () => {
  // Attach click event to labels inside .opt-field-dropdown
  $('.opt-field-dropdown label').on('click', function () {
    const $optField = $(this).closest('.opt-field');
    setTimeout(() => {
      const anyChecked = $optField.find('input:checked').length > 0;
      $optField.toggleClass('selected-field', anyChecked);
    }, 0);
  });

  // Also run on page load to catch pre-checked inputs
  $('.opt-field').each(function () {
    const anyChecked = $(this).find('input:checked').length > 0;
    $(this).toggleClass('selected-field', anyChecked);
  });
};


