class Admin {
  constructor(){
    this.initSearchCategories()
    this.initRefundType()
  }
  initSearchCategories(){
    const self = this

    $('#search')
      .off('keyup')
      .on('keyup',function() {
        const searched = $(this).val().toLowerCase()

        self.filterList(searched)

      })
  }
  initRefundType(){
    $('[name="refund_type"]')
      .off('change')
      .on('change',function(){
        $('[data-woocommerce-status]').toggleClass('d-none',$(this).val()!='auto')
      })
  }
  filterList(searched){
    $('[data-category-name]').each(function(){
      $(this).toggleClass(
        'd-none',
        $(this).data('category-name').toLowerCase().indexOf(searched)==-1
      )
    })
  }
}

jQuery(document).ready(function($){
  window.$ = $
  new Admin;
});