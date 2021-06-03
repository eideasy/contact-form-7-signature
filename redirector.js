document.addEventListener('wpcf7mailsent', function (event) {
  console.log('mail sent data', event)
  let unitTag = event.detail.unitTag
  if (!unitTag) {
    unitTag = event.detail.id // for older versions
  }
  let params = '?action=eideasy_signing_url&unit_tag=' + unitTag
  fetch(eideasy_settings.ajaxUrl + params).then(response => response.json()).then(responseJson => {
    console.log(responseJson)
    if (responseJson.signing_url) {
      location = responseJson.signing_url
    } else {
      console.log('Not redirecting as signing url not found')
    }
  }, false)
})
