import axios from 'axios'

const TOKEN_KEY = 'ilg_token'
const USER_KEY = 'ilg_user'

const client = axios.create({
  baseURL: import.meta.env.VITE_API_URL || '/api/v1',
  headers: {
    Accept: 'application/json',
    'Content-Type': 'application/json',
  },
  withCredentials: false,
})

client.interceptors.request.use((config) => {
  const token = localStorage.getItem(TOKEN_KEY)
  if (token) {
    config.headers.Authorization = `Bearer ${token}`
  }
  return config
})

client.interceptors.response.use(
  (response) => response,
  (error) => {
    if (error.response?.status === 401) {
      localStorage.removeItem(TOKEN_KEY)
      localStorage.removeItem(USER_KEY)
      const path = window.location.pathname
      if (!path.startsWith('/login') && !path.startsWith('/health')) {
        window.location.assign('/login')
      }
    }
    return Promise.reject(error)
  },
)

export function setAuthToken(token) {
  if (token) localStorage.setItem(TOKEN_KEY, token)
  else localStorage.removeItem(TOKEN_KEY)
}

export function getAuthToken() {
  return localStorage.getItem(TOKEN_KEY)
}

export { TOKEN_KEY, USER_KEY }

export default client
