import client from './client'

export async function login(email, password, deviceName = 'spa') {
  const { data } = await client.post('/auth/login', {
    email,
    password,
    device_name: deviceName,
  })
  return data
}

export async function logout() {
  const { data } = await client.post('/auth/logout')
  return data
}

export async function fetchMe() {
  const { data } = await client.get('/auth/me')
  return data
}
