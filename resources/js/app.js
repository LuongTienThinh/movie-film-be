$(document).ready(function() {
    $('input, textarea').attr('spellcheck', 'false');
});

$(document).ready(function () {
    function updateActiveMenuStorage() {
        var currentHref = window.location.href;

        // Ưu tiên menu con
        var $activeSubItem = $('aside#menu .nav-second-level li a[href="' + currentHref + '"]');
        if ($activeSubItem.length) {
            localStorage.setItem('activeMenu', currentHref);
            return;
        }

        // Nếu không có submenu thì lấy menu cha
        var $activeMainItem = $('aside#menu > ul > li > a[href="' + currentHref + '"]');
        if ($activeMainItem.length) {
            localStorage.setItem('activeMenu', currentHref);
            return;
        }
        
        if (currentHref.endsWith('/')) {
            currentHref = currentHref.slice(0, -1);
        } else {
            currentHref = currentHref + '/';
        }

        $activeMainItem = $('aside#menu > ul > li > a[href="' + currentHref + '"]');
        if ($activeMainItem.length) {
            localStorage.setItem('activeMenu', currentHref);
            return;
        }
    }
    updateActiveMenuStorage();

    function restoreMenuFromStorage() {
        var savedHref = localStorage.getItem('activeMenu');
        if (!savedHref) return;
    
        var $link = $('aside#menu a[href="' + savedHref + '"]');
        if (!$link.length) return;
    
        var $li = $link.closest('li');
        var $mainItem = $link.closest('li[class^="menu-item-"]');
    
        // Xóa trạng thái cũ
        $('aside#menu li').removeClass('active');
        $('aside#menu li[class^="menu-item-"]').removeClass('menu-item-open');
    
        // Active li chứa link
        $li.addClass('active');
    
        // Nếu là menu con thì active menu cha
        if ($link.parents('.nav-second-level').length) {
            $mainItem.addClass('menu-item-open active');
            $mainItem.find('ul').addClass('in');
        } else {
            // Nếu là menu cha thì active luôn
            $mainItem.addClass('active');
        }
        console.log($mainItem.position().top - 70);

        if ($mainItem.length) {
            $('aside#menu').animate({
                scrollTop: $mainItem.position().top - 70
            }, 200);
        }
    }

    // Khôi phục trạng thái menu khi load trang
    restoreMenuFromStorage();

    // Cập nhật khi click vào menu
    $('aside#menu a').on('click', function () {
        setTimeout(updateActiveMenuStorage, 100);
    });

    // Cập nhật khi URL thay đổi
    $(window).on('popstate hashchange', function () {
        updateActiveMenuStorage();
    });
});